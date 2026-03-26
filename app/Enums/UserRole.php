<?php

namespace App\Enums;

enum UserRole: string
{
    case Ceo = 'ceo';
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Ceo => 'CEO',
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Operator => 'Operador',
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->level() >= $minimum->level();
    }

    public function canManageRoles(): bool
    {
        return $this === self::Ceo;
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::Ceo, self::Admin], true);
    }

    private function level(): int
    {
        return match ($this) {
            self::Operator => 1,
            self::Supervisor => 2,
            self::Admin => 3,
            self::Ceo => 4,
        };
    }
}
