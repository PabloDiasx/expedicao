<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Operator => 'Operador',
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->level() >= $minimum->level();
    }

    private function level(): int
    {
        return match ($this) {
            self::Operator => 1,
            self::Supervisor => 2,
            self::Admin => 3,
        };
    }
}
