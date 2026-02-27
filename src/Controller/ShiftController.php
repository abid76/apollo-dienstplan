<?php

namespace App\Controller;

use App\Service\ShiftService;

class ShiftController
{
    private ShiftService $service;

    public function __construct()
    {
        $this->service = new ShiftService();
    }

    public function index(): void
    {
        $shifts = $this->service->list();
        $content = $this->renderView('shifts/list', ['shifts' => $shifts]);
        $this->renderLayout($content);
    }

    public function create(): void
    {
        $content = $this->renderView('shifts/form', [
            'action' => BASE_PATH . '/shifts/create',
            'shift' => null,
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function store(): void
    {
        $errors = $this->service->create($_POST);
        if ($errors) {
            $content = $this->renderView('shifts/form', [
                'action' => BASE_PATH . '/shifts/create',
                'shift' => $_POST,
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }
        header('Location: ' . BASE_PATH . '/shifts');
        exit;
    }

    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $shift = $this->service->get($id);
        if (!$shift) {
            http_response_code(404);
            echo 'Schicht nicht gefunden.';
            return;
        }
        $content = $this->renderView('shifts/form', [
            'action' => BASE_PATH . '/shifts/edit?id=' . $id,
            'shift' => $shift,
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
            $content = $this->renderView('shifts/form', [
                'action' => BASE_PATH . '/shifts/edit?id=' . $id,
                'shift' => $data,
                'errors' => $errors,
            ]);
            $this->renderLayout($content);
            return;
        }
        header('Location: ' . BASE_PATH . '/shifts');
        exit;
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->delete($id);
        }
        header('Location: ' . BASE_PATH . '/shifts');
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

