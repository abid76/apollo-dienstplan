<?php

namespace App\Controller;

use App\Service\PlanService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PlanController
{
    private PlanService $service;

    public function __construct()
    {
        $this->service = new PlanService();
    }

    public function index(): void
    {
        $plans = $this->service->listPlans();
        $content = $this->renderView('plan/list', ['plans' => $plans]);
        $this->renderLayout($content);
    }

    public function form(): void
    {
        $content = $this->renderView('plan/form', [
            'errors' => [],
        ]);
        $this->renderLayout($content);
    }

    public function delete(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $this->service->deletePlan($id);
        }
        header('Location: ' . BASE_PATH . '/plan');
        exit;
    }

    public function generate(): void
    {
        $startDate = $_POST['start_date'] ?? '';
        $weeks = (int)($_POST['weeks'] ?? 1);
        $errors = [];

        if (!$startDate) {
            $errors[] = 'Startdatum ist erforderlich.';
        } elseif ((new \DateTime($startDate))->format('N') !== '1') {
            $errors[] = 'Das Startdatum muss ein Montag sein.';
        }
        if ($weeks < 1) {
            $errors[] = 'Anzahl Wochen muss mindestens 1 sein.';
        }

        if ($errors) {
            $content = $this->renderView('plan/form', [
                'errors' => $errors,
                'submitted_start_date' => $startDate,
                'submitted_weeks' => $weeks,
            ]);
            $this->renderLayout($content);
            return;
        }

        $planId = $this->service->generate($startDate, $weeks);

        header('Location: ' . BASE_PATH . '/plan/show?id=' . $planId);
        exit;
    }

    public function show(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $data = $this->service->getPlanViewData($id);
        if (!$data) {
            http_response_code(404);
            echo 'Plan nicht gefunden.';
            return;
        }

        $content = $this->renderView('plan/show', $data);
        $this->renderLayout($content);
    }

    public function export(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $spreadsheet = $this->service->createSpreadsheetForPlan($id);

        if ($spreadsheet === null) {
            http_response_code(404);
            echo 'Plan nicht gefunden.';
            return;
        }

        $fileName = 'dienstplan_' . $id . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
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

