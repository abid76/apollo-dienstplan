<?php

namespace App\Controller;

use App\Service\HolidayService;

class HolidayController
{
    private HolidayService $service;

    public function __construct()
    {
        $this->service = new HolidayService();
    }

    public function index(): void
    {
        $holidays = $this->service->list();
        $content = $this->renderView('holidays/list', ['holidays' => $holidays]);
        $this->renderLayout($content);
    }

    public function create(): void
    {
        $formData = $this->service->getFormData();
        $content = $this->renderView('holidays/form', [
            'action' => BASE_PATH . '/holidays/create',
            'holiday' => $formData['holiday'],
            'employees' => $formData['employees'],
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function store(): void
    {
        $errors = $this->service->create($_POST);
        if ($errors) {
            $formData = $this->service->getFormData();
            $content = $this->renderView('holidays/form', [
                'action' => BASE_PATH . '/holidays/create',
                'holiday' => $_POST,
                'employees' => $formData['employees'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: ' . BASE_PATH . '/holidays');
        exit;
    }

    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $formData = $this->service->getFormData($id);
        if (!$formData['holiday']) {
            http_response_code(404);
            echo 'Eintrag nicht gefunden.';
            return;
        }

        $content = $this->renderView('holidays/form', [
            'action' => BASE_PATH . '/holidays/edit?id=' . $id,
            'holiday' => $formData['holiday'],
            'employees' => $formData['employees'],
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
            $holiday = array_merge($formData['holiday'] ?? [], $_POST);
            $content = $this->renderView('holidays/form', [
                'action' => BASE_PATH . '/holidays/edit?id=' . $id,
                'holiday' => $holiday,
                'employees' => $formData['employees'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: ' . BASE_PATH . '/holidays');
        exit;
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->delete($id);
        }
        header('Location: ' . BASE_PATH . '/holidays');
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

