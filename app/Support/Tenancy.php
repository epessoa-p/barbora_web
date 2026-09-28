<?php

namespace App\Support;

use App\Models\Company;

/**
 * Empresa activa de la petición actual.
 *
 * Distingue tres estados, y la diferencia entre los dos últimos es crítica:
 *
 *   - CON empresa     → las consultas se filtran por ese company_id.
 *   - SIN empresa     → fail-closed: las consultas no devuelven NADA.
 *   - SIN restricción → superadmin en Modo Global: las consultas no se filtran.
 *
 * Si "sin empresa" significara "sin filtro", cualquier usuario que perdiera su
 * empresa activa (sesión expirada, cookie borrada) vería los datos de todas las
 * barberías. Ver ARQUITECTURA §4.
 */
class Tenancy
{
    protected ?int $companyId = null;

    protected bool $unrestricted = false;

    protected ?Company $company = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
        $this->unrestricted = false;
        $this->company = null;
    }

    public function setCompany(?Company $company): void
    {
        $this->companyId = $company?->id;
        $this->unrestricted = false;
        $this->company = $company;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function company(): ?Company
    {
        if ($this->company === null && $this->companyId !== null) {
            $this->company = Company::find($this->companyId);
        }

        return $this->company;
    }

    public function hasTenant(): bool
    {
        return $this->companyId !== null;
    }

    /**
     * Modo Global: sin filtro de empresa. Solo para el superadmin y la consola.
     */
    public function unrestrict(bool $value = true): void
    {
        $this->unrestricted = $value;

        if ($value) {
            $this->companyId = null;
            $this->company = null;
        }
    }

    public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    public function forget(): void
    {
        $this->companyId = null;
        $this->unrestricted = false;
        $this->company = null;
    }

    /**
     * Ejecuta un callback con otra empresa activa y restaura el estado anterior.
     */
    public function runFor(?int $companyId, callable $callback): mixed
    {
        $previous = [$this->companyId, $this->unrestricted, $this->company];

        $this->set($companyId);

        try {
            return $callback();
        } finally {
            [$this->companyId, $this->unrestricted, $this->company] = $previous;
        }
    }

    /**
     * Ejecuta un callback sin filtro de empresa (seeders, reportes del operador).
     */
    public function runUnrestricted(callable $callback): mixed
    {
        $previous = [$this->companyId, $this->unrestricted, $this->company];

        $this->unrestrict();

        try {
            return $callback();
        } finally {
            [$this->companyId, $this->unrestricted, $this->company] = $previous;
        }
    }
}
