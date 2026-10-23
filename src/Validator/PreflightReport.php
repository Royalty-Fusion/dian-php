<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

/**
 * Result of {@see \RoyaltyFusion\DianPhp\Dian::preflight()}: every local check
 * grouped by section (reglas, certificado, xsd, firma, consistencia).
 *
 * isReady() === true means nothing found locally would make DIAN reject the
 * document; warnings are listed but do not block.
 */
final class PreflightReport
{
    /** @var array<string,ValidationResult> */
    private array $sections = [];
    private string $signedXml = '';
    private string $uuid = '';

    public function add(string $section, ValidationResult $result): self
    {
        if (isset($this->sections[$section])) {
            $this->sections[$section]->merge($result);
        } else {
            $this->sections[$section] = $result;
        }
        return $this;
    }

    public function isReady(): bool
    {
        foreach ($this->sections as $result) {
            if (!$result->isValid()) {
                return false;
            }
        }
        return true;
    }

    /** @return list<ValidationError> */
    public function errors(): array
    {
        return $this->filter('error');
    }

    /** @return list<ValidationError> */
    public function warnings(): array
    {
        return $this->filter('warning');
    }

    /** @return array<string,ValidationResult> */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function setSignedXml(string $signedXml): self
    {
        $this->signedXml = $signedXml;
        return $this;
    }

    /** Signed XML produced during the dry run (empty if it could not be built). */
    public function getSignedXml(): string
    {
        return $this->signedXml;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** @return list<string> "[CODE] message" for every error */
    public function messages(): array
    {
        return array_map(static fn (ValidationError $e): string => "[{$e->code}] {$e->message}", $this->errors());
    }

    /** @return array{ready:bool,sections:array<string,list<array{code:string,message:string,severity:string,path:string}>>} */
    public function toArray(): array
    {
        $sections = [];
        foreach ($this->sections as $name => $result) {
            $sections[$name] = array_map(static fn (ValidationError $e): array => [
                'code'     => $e->code,
                'message'  => $e->message,
                'severity' => $e->severity,
                'path'     => $e->xpath,
            ], $result->getErrors());
        }

        return ['ready' => $this->isReady(), 'sections' => $sections];
    }

    public function toText(): string
    {
        $out = [$this->isReady()
            ? 'LISTO: no se encontraron errores que la DIAN rechace.'
            : 'NO LISTO: corrija los errores antes de enviar.'];
        foreach ($this->sections as $name => $result) {
            $errors = $result->getErrors();
            $out[]  = sprintf('%s %s', $result->isValid() ? '[ok]' : '[!!]', $name . ($errors === [] ? '' : ':'));
            foreach ($errors as $e) {
                $out[] = sprintf('    %s %s %s', $e->severity === 'error' ? 'ERROR' : 'aviso', $e->code, $e->message);
            }
        }

        return implode(PHP_EOL, $out);
    }

    /** @return list<ValidationError> */
    private function filter(string $severity): array
    {
        $out = [];
        foreach ($this->sections as $result) {
            foreach ($result->getErrors() as $error) {
                if ($error->severity === $severity) {
                    $out[] = $error;
                }
            }
        }
        return $out;
    }
}
