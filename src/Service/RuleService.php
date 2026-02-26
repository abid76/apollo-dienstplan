<?php

namespace App\Service;

use App\Repository\RuleRepository;
use App\Repository\ShiftRepository;
use App\Repository\RoleRepository;

class RuleService
{
    private RuleRepository $rules;
    private ShiftRepository $shifts;
    private RoleRepository $roles;

    public function __construct()
    {
        $this->rules = new RuleRepository();
        $this->shifts = new ShiftRepository();
        $this->roles = new RoleRepository();
    }

    public function listWithDetails(): array
    {
        return $this->rules->findAllWithDetails();
    }

    public function get(int $id): ?array
    {
        return $this->rules->find($id);
    }

    public function getFormData(?int $id = null): array
    {
        $rule = null;
        if ($id !== null) {
            $rule = $this->get($id);
        }

        return [
            'rule' => $rule,
            'shifts' => $this->shifts->findAll(),
            'roles' => $this->roles->findAll(),
        ];
    }

    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->rules->create(
            (int)$data['shift_id'],
            (int)$data['role_id'],
            (int)$data['required_count']
        );

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->rules->update(
            $id,
            (int)$data['shift_id'],
            (int)$data['role_id'],
            (int)$data['required_count']
        );

        return [];
    }

    public function delete(int $id): void
    {
        $this->rules->delete($id);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['shift_id'] ?? '')) {
            $errors[] = 'Schicht ist erforderlich.';
        }
        if (empty($data['role_id'] ?? '')) {
            $errors[] = 'Rolle ist erforderlich.';
        }
        if (!isset($data['required_count']) || $data['required_count'] === '') {
            $errors[] = 'Anzahl ist erforderlich.';
        } elseif (!is_numeric($data['required_count']) || (int)$data['required_count'] < 1) {
            $errors[] = 'Anzahl muss eine positive Zahl sein.';
        }

        return $errors;
    }
}

