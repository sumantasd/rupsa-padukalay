<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ReportPdfService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ReportPdfController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportPdfService $reportPdfService
    ) {}

    public function export(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->errorResponse('Unauthenticated. Please log in.', 401);
            }

            $type = $request->input('type');
            if (empty($type) || $type === 'undefined' || $type === 'null') {
                return $this->errorResponse('Valid report type parameter is required.', 422);
            }

            $action = $request->input('action', 'inline'); // inline or attachment

            return $this->reportPdfService->generatePdf($user, $request->all(), $action);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF Generation Failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $request->user()?->id,
                'params' => $request->all(),
            ]);

            return $this->errorResponse('Failed to generate PDF report: ' . $e->getMessage(), 500);
        }
    }
}
