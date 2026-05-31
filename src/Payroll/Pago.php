<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

class Pago
{
    private string $forma = '1';        // 1=Contado, 2=Crédito
    private string $metodo = '10';      // 10=Efectivo, 42=Consignación, etc.
    private string $banco = '';
    private string $tipoCuenta = '';
    private string $numeroCuenta = '';

    public function setForma(string $v): self { $this->forma = $v; return $this; }
    public function getForma(): string { return $this->forma; }
    public function setMetodo(string $v): self { $this->metodo = $v; return $this; }
    public function getMetodo(): string { return $this->metodo; }
    public function setBanco(string $v): self { $this->banco = $v; return $this; }
    public function getBanco(): string { return $this->banco; }
    public function setTipoCuenta(string $v): self { $this->tipoCuenta = $v; return $this; }
    public function getTipoCuenta(): string { return $this->tipoCuenta; }
    public function setNumeroCuenta(string $v): self { $this->numeroCuenta = $v; return $this; }
    public function getNumeroCuenta(): string { return $this->numeroCuenta; }
}
