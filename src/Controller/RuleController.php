<?php

namespace App\Controller;

use App\Service\RuleService;

class RuleController
{
    private RuleService $service;

    public function __construct()
    {
        $this->service = new RuleService();
    }

    public function index(): void
    {
        $rules = $this->service->listWithDetails();
        $content = $this->renderView('rules/list', ['rules' => $rules]);
        $this->renderLayout($content);
    }

    public function create(): void
    {
        $formData = $this->service->getFormData();
        $content = $this->renderView('rules/form', [
            'action' => '/rules/create',
            'rule' => $formData['rule'],
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
            $content = $this->renderView('rules/form', [
                'action' => '/rules/create',
                'rule' => $_POST,
                'shifts' => $formData['shifts'],
                'roles' => $formData['roles'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: /rules');
        exit;
    }

    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $formData = $this->service->getFormData($id);
        if (!$formData['rule']) {
            http_response_code(404);
            echo 'Regel nicht gefunden.';
            return;
        }

        $content = $this->renderView('rules/form', [
            'action' => '/rules/edit?id=' . $id,
            'rule' => $formData['rule'],
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
            $rule = array_merge($formData['rule'] ?? [], $_POST);
            $content = $this->renderView('rules/form', [
                'action' => '/rules/edit?id=' . $id,
                'rule' => $rule,
                'shifts' => $formData['shifts'],
                'roles' => $formData['roles'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: /rules');
        exit;
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->delete($id);
        }
        header('Location: /rules');
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

