<?php

namespace App\Controller;

use App\Service\RoleService;

class RoleController
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService();
    }

    public function index(): void
    {
        $roles = $this->service->list();
        $content = $this->renderView('roles/list', ['roles' => $roles]);
        $this->renderLayout($content);
    }

    public function create(): void
    {
        $content = $this->renderView('roles/form', [
            'action' => BASE_PATH . '/roles/create',
            'role' => null,
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function store(): void
    {
        $errors = $this->service->create($_POST);
        if ($errors) {
            $content = $this->renderView('roles/form', [
                'action' => BASE_PATH . '/roles/create',
                'role' => $_POST,
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }
        header('Location: ' . BASE_PATH . '/roles');
        exit;
    }

    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $role = $this->service->get($id);
        if (!$role) {
            http_response_code(404);
            echo 'Rolle nicht gefunden.';
            return;
        }
        $content = $this->renderView('roles/form', [
            'action' => BASE_PATH . '/roles/edit?id=' . $id,
            'role' => $role,
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function update(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $errors = $this->service->update($id, $_POST);
        if ($errors) {
            $data = $_POST;
            $data['id'] = $id;
            $content = $this->renderView('roles/form', [
                'action' => BASE_PATH . '/roles/edit?id=' . $id,
                'role' => $data,
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }
        header('Location: ' . BASE_PATH . '/roles');
        exit;
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->delete($id);
        }
        header('Location: ' . BASE_PATH . '/roles');
        exit;
    }

    private function renderView(string $view, array $params = []): string
    {
        extract($params);
        ob_start();
        require __DIR__ . '/../../views/' . $view . '.php';
        return ob_get_clean();
    }

    private function renderLayout(string $content): void
    {
        require __DIR__ . '/../../views/layout.php';
    }
}

