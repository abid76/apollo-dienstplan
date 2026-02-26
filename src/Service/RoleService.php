<?php

namespace App\Service;

use App\Repository\RoleRepository;

class RoleService
{
    private RoleRepository $repository;

    public function __construct()
    {
        $this->repository = new RoleRepository();
    }

    public function list(): array
    {
        return $this->repository->findAll();
    }

    public function get(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->repository->create(
            trim($data['name']),
            trim($data['shortcode'])
        );

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->repository->update(
            $id,
            trim($data['name']),
            trim($data['shortcode'])
        );

        return [];
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = 'Bezeichnung ist erforderlich.';
        }
        if (empty(trim($data['shortcode'] ?? ''))) {
            $errors[] = 'Kürzel ist erforderlich.';
        }
        return $errors;
    }
}

