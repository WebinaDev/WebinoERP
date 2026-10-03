<?php

namespace Modules\Integrations\Services;

class SpamFilter
{
    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function apply(array $message): array
    {
        $score = 0;
        $subject = strtolower((string) ($message['subject'] ?? ''));
        $body = strtolower((string) ($message['body'] ?? ''));
        $from = strtolower((string) ($message['from_email'] ?? ''));
        $blob = $subject.' '.$body;

        foreach ((array) config('integrations.spam.keywords', []) as $keyword) {
            $needle = strtolower((string) $keyword);
            if ($needle !== '' && str_contains($blob, $needle)) {
                $score += 2;
            }
        }
        $domain = '';
        if (str_contains($from, '@')) {
            $domain = substr($from, (int) strrpos($from, '@') + 1);
        }
        foreach ((array) config('integrations.spam.domains', []) as $blocked) {
            if ($domain !== '' && $domain === strtolower((string) $blocked)) {
                $score += 3;
            }
        }
        if (! empty($message['spam_flag'])) {
            $score += 4;
        }
        if ($subject === '') {
            $score += 1;
        }
        if (substr_count($body, 'http') >= 3) {
            $score += 1;
        }

        $threshold = (int) config('integrations.spam.threshold', 3);
        $spam = $score >= $threshold;
        $message['spam_score'] = $score;
        $message['is_spam'] = $spam;
        $message['folder'] = $spam ? 'spam' : (string) ($message['folder'] ?? 'inbox');
        unset($message['spam_flag']);

        return $message;
    }
}
