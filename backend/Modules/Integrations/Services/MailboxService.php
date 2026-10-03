<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Crm\Entities\CrmActivity;
use Modules\Integrations\Entities\EmailAccount;
use Modules\Integrations\Entities\EmailMessage;

class MailboxService
{
    /**
     * @return array{imported: int}
     */
    public function sync(EmailAccount $account): array
    {
        $rows = match ($account->provider) {
            'google' => $this->pullGmail($account),
            'microsoft' => $this->pullGraph($account),
            default => $this->pullImap($account),
        };
        $imported = 0;
        foreach ($rows as $row) {
            $message = EmailMessage::query()->updateOrCreate(
                ['account_id' => $account->id, 'external_id' => $row['external_id']],
                $row
            );
            $this->linkByAddress($message);
            $imported++;
        }

        return ['imported' => $imported];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function import(EmailAccount $account, array $data): EmailMessage
    {
        $message = EmailMessage::query()->create(array_merge($data, [
            'account_id' => $account->id,
            'direction' => $data['direction'] ?? 'in',
            'external_id' => $data['external_id'] ?? ('local-'.uniqid()),
        ]));
        $this->linkByAddress($message);

        return $message->fresh();
    }

    /**
     * @param  array{to: string, subject: string, body: string}  $draft
     */
    public function send(EmailAccount $account, array $draft): EmailMessage
    {
        $external = 'local-'.uniqid();
        if ($account->provider === 'google' && $account->access_token) {
            $raw = $this->rfc822($account->email, $draft['to'], $draft['subject'], $draft['body']);
            $response = Http::withToken((string) $account->access_token)->timeout(20)->post(
                'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
                ['raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=')]
            );
            if ($response->successful()) {
                $external = (string) ($response->json('id') ?: $external);
            }
        } elseif ($account->provider === 'microsoft' && $account->access_token) {
            $response = Http::withToken((string) $account->access_token)->timeout(20)->post(
                'https://graph.microsoft.com/v1.0/me/sendMail',
                ['message' => [
                    'subject' => $draft['subject'],
                    'body' => ['contentType' => 'Text', 'content' => $draft['body']],
                    'toRecipients' => [['emailAddress' => ['address' => $draft['to']]]],
                ], 'saveToSentItems' => true]
            );
            if (! $response->successful()) {
                throw new \RuntimeException('Graph send failed.');
            }
        } elseif ($account->smtp_host) {
            $this->smtpSend($account, $draft['to'], $draft['subject'], $draft['body']);
        }

        return EmailMessage::query()->create([
            'account_id' => $account->id,
            'external_id' => $external,
            'thread_key' => $draft['thread_key'] ?? $external,
            'direction' => 'out',
            'from_email' => $account->email,
            'to_email' => $draft['to'],
            'subject' => $draft['subject'],
            'body' => $draft['body'],
            'sent_at' => now(),
        ]);
    }

    public function attachActivity(EmailMessage $message, ?int $crmAccountId = null): CrmActivity
    {
        $accountId = $crmAccountId ?: $message->crm_account_id;
        $activity = CrmActivity::query()->create([
            'type' => 'email',
            'subject' => (string) ($message->subject ?: 'Email'),
            'description' => (string) $message->body,
            'related_model' => $accountId ? 'account' : 'email',
            'related_id' => $accountId,
            'completed_at' => $message->sent_at ?? now(),
            'created_by' => $message->account?->user_id,
        ]);
        $message->update([
            'crm_account_id' => $accountId,
            'activity_id' => $activity->id,
        ]);

        return $activity;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pullGmail(EmailAccount $account): array
    {
        $list = Http::withToken((string) $account->access_token)->timeout(20)->get(
            'https://gmail.googleapis.com/gmail/v1/users/me/messages',
            ['maxResults' => 15]
        );
        if (! $list->successful()) {
            throw new \RuntimeException('Gmail list failed.');
        }
        $rows = [];
        foreach ((array) $list->json('messages', []) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $full = Http::withToken((string) $account->access_token)->timeout(20)->get(
                'https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$item['id'],
                ['format' => 'full']
            );
            if (! $full->successful()) {
                continue;
            }
            $headers = collect((array) $full->json('payload.headers', []))->mapWithKeys(
                fn ($header) => [strtolower((string) ($header['name'] ?? '')) => (string) ($header['value'] ?? '')]
            );
            $rows[] = [
                'external_id' => (string) $item['id'],
                'thread_key' => (string) ($full->json('threadId') ?: $item['id']),
                'direction' => 'in',
                'from_email' => $this->address((string) $headers->get('from', '')),
                'to_email' => $this->address((string) $headers->get('to', '')),
                'subject' => (string) $headers->get('subject', ''),
                'body' => (string) ($full->json('snippet') ?: ''),
                'attachments' => $this->gmailAttachments((array) $full->json('payload', [])),
                'sent_at' => now(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pullGraph(EmailAccount $account): array
    {
        $list = Http::withToken((string) $account->access_token)->timeout(20)->get(
            'https://graph.microsoft.com/v1.0/me/messages',
            ['$top' => 15, '$select' => 'id,conversationId,subject,bodyPreview,from,toRecipients,receivedDateTime,hasAttachments']
        );
        if (! $list->successful()) {
            throw new \RuntimeException('Graph mail list failed.');
        }
        $rows = [];
        foreach ((array) $list->json('value', []) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $rows[] = [
                'external_id' => (string) $item['id'],
                'thread_key' => (string) ($item['conversationId'] ?? $item['id']),
                'direction' => 'in',
                'from_email' => (string) data_get($item, 'from.emailAddress.address', ''),
                'to_email' => (string) data_get($item, 'toRecipients.0.emailAddress.address', ''),
                'subject' => (string) ($item['subject'] ?? ''),
                'body' => (string) ($item['bodyPreview'] ?? ''),
                'attachments' => ! empty($item['hasAttachments']) ? [['name' => 'attachment']] : [],
                'sent_at' => $item['receivedDateTime'] ?? now(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pullImap(EmailAccount $account): array
    {
        if (! $account->imap_host) {
            return [];
        }
        if (function_exists('imap_open')) {
            return $this->pullImapExtension($account);
        }

        return $this->pullImapSocket($account);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pullImapExtension(EmailAccount $account): array
    {
        $mailbox = sprintf('{%s:%d/imap/ssl}INBOX', $account->imap_host, (int) ($account->imap_port ?: 993));
        $stream = @imap_open($mailbox, (string) $account->username, (string) $account->password, OP_READONLY, 1);
        if (! $stream) {
            throw new \RuntimeException('IMAP login failed.');
        }
        $ids = imap_search($stream, 'ALL') ?: [];
        $ids = array_slice($ids, -10);
        $rows = [];
        foreach ($ids as $id) {
            $header = imap_headerinfo($stream, (int) $id);
            $body = (string) imap_body($stream, (int) $id, FT_PEEK);
            $rows[] = [
                'external_id' => 'imap-'.(string) ($header->message_id ?? $id),
                'thread_key' => (string) ($header->message_id ?? $id),
                'direction' => 'in',
                'from_email' => (string) ($header->from[0]->mailbox ?? '').'@'.(string) ($header->from[0]->host ?? ''),
                'to_email' => $account->email,
                'subject' => isset($header->subject) ? imap_utf8((string) $header->subject) : '',
                'body' => $body,
                'attachments' => [],
                'sent_at' => isset($header->date) ? date('c', strtotime((string) $header->date)) : now(),
            ];
        }
        imap_close($stream);

        return $rows;
    }

    /**
     * Minimal IMAP over SSL for hosts without the imap extension.
     *
     * @return list<array<string, mixed>>
     */
    private function pullImapSocket(EmailAccount $account): array
    {
        $port = (int) ($account->imap_port ?: 993);
        $socket = @stream_socket_client('ssl://'.$account->imap_host.':'.$port, $errno, $errstr, 12);
        if (! $socket) {
            throw new \RuntimeException('IMAP connection failed: '.$errstr);
        }
        stream_set_timeout($socket, 12);
        $this->imapRead($socket);
        $this->imapCmd($socket, 'a1', 'LOGIN '.$this->imapQuote((string) $account->username).' '.$this->imapQuote((string) $account->password));
        $this->imapCmd($socket, 'a2', 'SELECT INBOX');
        $search = $this->imapCmd($socket, 'a3', 'UID SEARCH ALL');
        preg_match('/\* SEARCH(.*)/', $search, $match);
        $uids = array_values(array_filter(preg_split('/\s+/', trim($match[1] ?? '')) ?: []));
        $uids = array_slice($uids, -5);
        $rows = [];
        foreach ($uids as $uid) {
            $raw = $this->imapCmd($socket, 'a4'.$uid, 'UID FETCH '.$uid.' (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE MESSAGE-ID)] BODY.PEEK[TEXT])');
            $parsed = $this->parseRaw($raw);
            $rows[] = [
                'external_id' => 'imap-'.($parsed['message_id'] ?: $uid),
                'thread_key' => $parsed['message_id'] ?: $uid,
                'direction' => 'in',
                'from_email' => $parsed['from'],
                'to_email' => $account->email,
                'subject' => $parsed['subject'],
                'body' => $parsed['body'],
                'attachments' => [],
                'sent_at' => $parsed['date'] ?: now(),
            ];
        }
        $this->imapCmd($socket, 'a9', 'LOGOUT');
        fclose($socket);

        return $rows;
    }

    /**
     * @param  array{to: string, subject?: string, body?: string}  $draft
     */
    private function smtpSend(EmailAccount $account, string $to, string $subject, string $body): void
    {
        $port = (int) ($account->smtp_port ?: 465);
        $scheme = $port === 587 ? 'tcp' : 'ssl';
        $socket = @stream_socket_client($scheme.'://'.$account->smtp_host.':'.$port, $errno, $errstr, 12);
        if (! $socket) {
            throw new \RuntimeException('SMTP connection failed: '.$errstr);
        }
        $read = function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }

            return $data;
        };
        $write = function (string $cmd) use ($socket, $read): string {
            fwrite($socket, $cmd."\r\n");

            return $read();
        };
        $read();
        $write('EHLO webino.local');
        $user = base64_encode((string) $account->username);
        $pass = base64_encode((string) $account->password);
        $write('AUTH LOGIN');
        $write($user);
        $auth = $write($pass);
        if (! str_starts_with($auth, '235')) {
            throw new \RuntimeException('SMTP auth failed.');
        }
        $write('MAIL FROM:<'.$account->email.'>');
        $write('RCPT TO:<'.$to.'>');
        $write('DATA');
        fwrite($socket, $this->rfc822($account->email, $to, $subject, $body)."\r\n.\r\n");
        $read();
        $write('QUIT');
        fclose($socket);
    }

    public function parseRaw(string $raw): array
    {
        $from = '';
        $subject = '';
        $date = '';
        $messageId = '';
        if (preg_match('/^From:\s*(.+)$/mi', $raw, $m)) {
            $from = $this->address($m[1]);
        }
        if (preg_match('/^Subject:\s*(.+)$/mi', $raw, $m)) {
            $subject = trim($m[1]);
        }
        if (preg_match('/^Date:\s*(.+)$/mi', $raw, $m)) {
            $date = trim($m[1]);
        }
        if (preg_match('/^Message-ID:\s*(.+)$/mi', $raw, $m)) {
            $messageId = trim($m[1], " <>\r");
        }
        $body = '';
        if (preg_match("/\r\n\r\n(.*)$/s", $raw, $m)) {
            $body = trim($m[1]);
        }

        return ['from' => $from, 'subject' => $subject, 'date' => $date, 'message_id' => $messageId, 'body' => $body];
    }

    private function linkByAddress(EmailMessage $message): void
    {
        $email = strtolower((string) ($message->direction === 'out' ? $message->to_email : $message->from_email));
        if ($email === '') {
            return;
        }
        $contact = DB::table('crm_contacts')->whereRaw('lower(email) = ?', [$email])->whereNull('deleted_at')->first();
        if ($contact) {
            $message->crm_account_id = (int) $contact->account_id;
            $message->save();
            if (! $message->activity_id) {
                $this->attachActivity($message, (int) $contact->account_id);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function gmailAttachments(array $payload): array
    {
        $out = [];
        $walk = function (array $part) use (&$walk, &$out): void {
            $filename = (string) ($part['filename'] ?? '');
            if ($filename !== '') {
                $out[] = [
                    'name' => $filename,
                    'mime' => (string) ($part['mimeType'] ?? ''),
                    'size' => (int) ($part['body']['size'] ?? 0),
                    'attachment_id' => (string) ($part['body']['attachmentId'] ?? ''),
                ];
            }
            foreach ((array) ($part['parts'] ?? []) as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($payload);

        return $out;
    }

    private function address(string $header): string
    {
        if (preg_match('/<([^>]+)>/', $header, $m)) {
            return strtolower(trim($m[1]));
        }

        return strtolower(trim($header));
    }

    private function rfc822(string $from, string $to, string $subject, string $body): string
    {
        return 'From: '.$from."\r\nTo: ".$to."\r\nSubject: ".$subject."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$body;
    }

    private function imapQuote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * @param  resource  $socket
     */
    private function imapCmd($socket, string $tag, string $cmd): string
    {
        fwrite($socket, $tag.' '.$cmd."\r\n");

        return $this->imapRead($socket, $tag);
    }

    /**
     * @param  resource  $socket
     */
    private function imapRead($socket, ?string $tag = null): string
    {
        $data = '';
        while (($line = fgets($socket, 8192)) !== false) {
            $data .= $line;
            if ($tag && str_starts_with($line, $tag.' ')) {
                break;
            }
            if ($tag === null && preg_match('/^\* OK/', $line)) {
                break;
            }
        }

        return $data;
    }
}
