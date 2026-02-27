<?php

namespace App\Controller;

use App\Service\EmployeeService;

class EmployeeController
{
    private EmployeeService $service;

    public function __construct()
    {
        $this->service = new EmployeeService();
    }

    public function index(): void
    {
        $employees = $this->service->list();
        $content = $this->renderView('employees/list', ['employees' => $employees]);
        $this->renderLayout($content);
    }

    public function create(): void
    {
        $formData = $this->service->getFormData();
        $content = $this->renderView('employees/form', [
            'action' => BASE_PATH . '/employees/create',
            'employee' => $formData['employee'],
            'shifts' => $formData['shifts'],
            'roles' => $formData['roles'],
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function store(): void
    {
        $errors = $this->service->create($_POST);
        if ($errors) {
            $formData = $this->service->getFormData();
            $content = $this->renderView('employees/form', [
                'action' => BASE_PATH . '/employees/create',
                'employee' => $_POST,
                'shifts' => $formData['shifts'],
                'roles' => $formData['roles'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: ' . BASE_PATH . '/employees');
        exit;
    }

    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $formData = $this->service->getFormData($id);
        if (!$formData['employee']) {
            http_response_code(404);
            echo 'Mitarbeiter nicht gefunden.';
            return;
        }

        $content = $this->renderView('employees/form', [
            'action' => BASE_PATH . '/employees/edit?id=' . $id,
            'employee' => $formData['employee'],
            'shifts' => $formData['shifts'],
            'roles' => $formData['roles'],
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function update(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $errors = $this->service->update($id, $_POST);
        if ($errors) {
            $formData = $this->service->getFormData($id);
            $employee = array_merge($formData['employee'] ?? [], $_POST);
            $content = $this->renderView('employees/form', [
                'action' => BASE_PATH . '/employees/edit?id=' . $id,
                'employee' => $employee,
                'shifts' => $formData['shifts'],
                'roles' => $formData['roles'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: ' . BASE_PATH . '/employees');
        exit;
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->delete($id);
        }
        header('Location: ' . BASE_PATH . '/employees');
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

