<?php

namespace App\Controller;

use App\Service\AbsenceService;

class AbsenceController
{
    private AbsenceService $service;

    public function __construct()
    {
        $this->service = new AbsenceService();
    }

    public function index(): void
    {
        $absences = $this->service->list();
        $content = $this->renderView('absences/list', ['absences' => $absences]);
        $this->renderLayout($content);
    }

    public function create(): void
    {
        $formData = $this->service->getFormData();
        $content = $this->renderView('absences/form', [
            'action' => BASE_PATH . '/absences/create',
            'absence' => $formData['absence'],
            'employees' => $formData['employees'],
            'shifts' => $formData['shifts'],
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function store(): void
    {
        $errors = $this->service->create($_POST);
        if ($errors) {
            $formData = $this->service->getFormData();
            $content = $this->renderView('absences/form', [
                'action' => BASE_PATH . '/absences/create',
                'absence' => $_POST,
                'employees' => $formData['employees'],
                'shifts' => $formData['shifts'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: ' . BASE_PATH . '/absences');
        exit;
    }

    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $formData = $this->service->getFormData($id);
        if (!$formData['absence']) {
            http_response_code(404);
            echo 'Eintrag nicht gefunden.';
            return;
        }

        $content = $this->renderView('absences/form', [
            'action' => BASE_PATH . '/absences/edit?id=' . $id,
            'absence' => $formData['absence'],
            'employees' => $formData['employees'],
            'shifts' => $formData['shifts'],
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
            $absence = array_merge($formData['absence'] ?? [], $_POST);
            $content = $this->renderView('absences/form', [
                'action' => BASE_PATH . '/absences/edit?id=' . $id,
                'absence' => $absence,
                'employees' => $formData['employees'],
                'shifts' => $formData['shifts'],
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }

        header('Location: ' . BASE_PATH . '/absences');
        exit;
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->delete($id);
        }
        header('Location: ' . BASE_PATH . '/absences');
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

