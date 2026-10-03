<?php

namespace Modules\Core\Services;

use App\Models\User;

class SamlIdpService
{
    /**
     * @return array{key: string, cert: string}
     */
    public function material(): array
    {
        $configuredKey = (string) config('integrations.saml.private_key');
        $configuredCert = (string) config('integrations.saml.certificate');
        if ($configuredKey !== '' && $configuredCert !== '') {
            return [
                'key' => str_replace('\\n', "\n", $configuredKey),
                'cert' => str_replace('\\n', "\n", $configuredCert),
            ];
        }
        $dir = storage_path('app/saml');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $keyPath = $dir.'/idp.key';
        $crtPath = $dir.'/idp.crt';
        if (is_file($keyPath) && is_file($crtPath)) {
            return ['key' => (string) file_get_contents($keyPath), 'cert' => (string) file_get_contents($crtPath)];
        }
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            throw new \RuntimeException('saml_keygen_failed');
        }
        openssl_pkey_export($resource, $key);
        $csr = openssl_csr_new(['CN' => 'Webino IdP', 'O' => 'Webino'], $resource, ['digest_alg' => 'sha256']);
        $crt = openssl_csr_sign($csr, null, $resource, 3650, ['digest_alg' => 'sha256']);
        openssl_x509_export($crt, $cert);
        file_put_contents($keyPath, $key);
        file_put_contents($crtPath, $cert);

        return ['key' => $key, 'cert' => $cert];
    }

    public function certificateBody(): string
    {
        $pem = $this->material()['cert'];
        $body = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);

        return is_string($body) ? $body : '';
    }

    public function metadataXml(int $providerId): string
    {
        $entity = htmlspecialchars(url('/api/v1/core/sso/saml/'.$providerId), ENT_QUOTES);
        $acs = htmlspecialchars(url('/api/v1/core/sso/saml/'.$providerId.'/acs'), ENT_QUOTES);
        $sso = htmlspecialchars(url('/api/v1/core/sso/saml/idp/sso'), ENT_QUOTES);
        $cert = $this->certificateBody();

        return '<?xml version="1.0"?>'
            .'<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="'.$entity.'">'
            .'<SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            .'<AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="'.$acs.'" index="0"/>'
            .'</SPSSODescriptor>'
            .'<IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            .'<KeyDescriptor use="signing"><KeyInfo xmlns="http://www.w3.org/2000/09/xmldsig#"><X509Data><X509Certificate>'.$cert.'</X509Certificate></X509Data></KeyInfo></KeyDescriptor>'
            .'<SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="'.$sso.'"/>'
            .'</IDPSSODescriptor>'
            .'</EntityDescriptor>';
    }

    /**
     * @return array{SAMLResponse: string, acs: string, xml: string}
     */
    public function issue(User $user, string $samlRequest): array
    {
        $requestXml = $this->decodeRequest($samlRequest);
        $acs = $this->first($requestXml, 'AssertionConsumerServiceURL') ?: url('/api/v1/core/sso/saml/idp/acs');
        $audience = $this->first($requestXml, 'Issuer') ?: $acs;
        $xml = $this->sign($this->responseXml($user->email, $acs, $audience));

        return [
            'SAMLResponse' => base64_encode($xml),
            'acs' => $acs,
            'xml' => $xml,
        ];
    }

    public function verify(string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return false;
        }
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $signedInfo = $xp->query('//ds:SignedInfo')->item(0);
        $digestNode = $xp->query('//ds:DigestValue')->item(0);
        $valueNode = $xp->query('//ds:SignatureValue')->item(0);
        $assertion = $xp->query('//*[local-name()="Assertion"]')->item(0);
        if (! $signedInfo instanceof \DOMElement || ! $digestNode || ! $valueNode || ! $assertion instanceof \DOMElement) {
            return false;
        }
        $digest = trim($digestNode->textContent);
        $signature = base64_decode(trim($valueNode->textContent), true);
        $info = $signedInfo->C14N(true, false);
        $localSignature = $xp->query('./ds:Signature', $assertion)->item(0);
        if ($localSignature) {
            $localSignature->parentNode?->removeChild($localSignature);
        }
        $calc = base64_encode(hash('sha256', $assertion->C14N(true, false), true));
        if (! is_string($signature) || ! hash_equals($digest, $calc)) {
            return false;
        }
        $public = openssl_pkey_get_public($this->material()['cert']);
        if ($public === false) {
            return false;
        }

        return openssl_verify($info, $signature, $public, OPENSSL_ALGO_SHA256) === 1;
    }

    private function sign(string $xml): string
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);
        $xp = new \DOMXPath($dom);
        $assertion = $xp->query('//*[local-name()="Assertion"]')->item(0);
        if (! $assertion instanceof \DOMElement) {
            throw new \RuntimeException('saml_assertion_missing');
        }
        $digest = base64_encode(hash('sha256', $assertion->C14N(true, false), true));
        $ds = 'http://www.w3.org/2000/09/xmldsig#';
        $signature = $dom->createElementNS($ds, 'ds:Signature');
        $signedInfo = $dom->createElementNS($ds, 'ds:SignedInfo');
        $canon = $dom->createElementNS($ds, 'ds:CanonicalizationMethod');
        $canon->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $method = $dom->createElementNS($ds, 'ds:SignatureMethod');
        $method->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $reference = $dom->createElementNS($ds, 'ds:Reference');
        $reference->setAttribute('URI', '#'.$assertion->getAttribute('ID'));
        $transforms = $dom->createElementNS($ds, 'ds:Transforms');
        $enveloped = $dom->createElementNS($ds, 'ds:Transform');
        $enveloped->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $exc = $dom->createElementNS($ds, 'ds:Transform');
        $exc->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $transforms->appendChild($enveloped);
        $transforms->appendChild($exc);
        $digestMethod = $dom->createElementNS($ds, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $digestValue = $dom->createElementNS($ds, 'ds:DigestValue', $digest);
        $reference->appendChild($transforms);
        $reference->appendChild($digestMethod);
        $reference->appendChild($digestValue);
        $signedInfo->appendChild($canon);
        $signedInfo->appendChild($method);
        $signedInfo->appendChild($reference);
        $signature->appendChild($signedInfo);
        $assertion->appendChild($signature);
        $info = $signedInfo->C14N(true, false);
        $private = openssl_pkey_get_private($this->material()['key']);
        if ($private === false) {
            throw new \RuntimeException('saml_key_unreadable');
        }
        openssl_sign($info, $raw, $private, OPENSSL_ALGO_SHA256);
        $signature->appendChild($dom->createElementNS($ds, 'ds:SignatureValue', base64_encode($raw)));
        $keyInfo = $dom->createElementNS($ds, 'ds:KeyInfo');
        $x509 = $dom->createElementNS($ds, 'ds:X509Data');
        $x509->appendChild($dom->createElementNS($ds, 'ds:X509Certificate', $this->certificateBody()));
        $keyInfo->appendChild($x509);
        $signature->appendChild($keyInfo);

        return (string) $dom->saveXML();
    }

    private function responseXml(string $email, string $acs, string $audience): string
    {
        $responseId = '_'.bin2hex(random_bytes(8));
        $assertionId = '_'.bin2hex(random_bytes(8));
        $instant = gmdate('Y-m-d\TH:i:s\Z');
        $notAfter = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
        $email = htmlspecialchars($email, ENT_QUOTES);
        $acs = htmlspecialchars($acs, ENT_QUOTES);
        $audience = htmlspecialchars($audience, ENT_QUOTES);
        $issuer = htmlspecialchars(url('/api/v1/core/sso/saml/idp'), ENT_QUOTES);

        return '<?xml version="1.0"?>'
            .'<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.$responseId.'" Version="2.0" IssueInstant="'.$instant.'" Destination="'.$acs.'">'
            .'<saml:Issuer>'.$issuer.'</saml:Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            .'<saml:Assertion ID="'.$assertionId.'" Version="2.0" IssueInstant="'.$instant.'">'
            .'<saml:Issuer>'.$issuer.'</saml:Issuer>'
            .'<saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.$email.'</saml:NameID>'
            .'<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="'.$notAfter.'" Recipient="'.$acs.'"/></saml:SubjectConfirmation>'
            .'</saml:Subject>'
            .'<saml:Conditions NotBefore="'.$instant.'" NotOnOrAfter="'.$notAfter.'"><saml:AudienceRestriction><saml:Audience>'.$audience.'</saml:Audience></saml:AudienceRestriction></saml:Conditions>'
            .'<saml:AuthnStatement AuthnInstant="'.$instant.'"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>'
            .'</saml:Assertion>'
            .'</samlp:Response>';
    }

    private function decodeRequest(string $raw): string
    {
        $decoded = base64_decode($raw, true);
        $candidate = is_string($decoded) && $decoded !== '' ? $decoded : $raw;
        $inflated = @gzinflate($candidate);
        if (is_string($inflated) && str_contains($inflated, '<')) {
            return $inflated;
        }
        if (str_contains($candidate, '<')) {
            return $candidate;
        }

        return $raw;
    }

    private function first(string $xml, string $local): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        if (! $dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return '';
        }
        $xp = new \DOMXPath($dom);
        if ($local === 'AssertionConsumerServiceURL') {
            $node = $xp->query('//*[@AssertionConsumerServiceURL]')->item(0);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $node instanceof \DOMElement ? (string) $node->getAttribute('AssertionConsumerServiceURL') : '';
        }
        $node = $xp->query('//*[local-name()="'.$local.'"]')->item(0);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $node ? trim($node->textContent) : '';
    }
}
