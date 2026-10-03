<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Requests\Moderation\CreateReportRequest;
use App\Http\Requests\Moderation\DecideReportRequest;
use App\Http\Requests\Moderation\ModerationRequest;
use App\Http\Requests\Moderation\ReportListRequest;
use App\Http\Resources\Moderation\ModerationReportResource;
use App\Http\Resources\Moderation\ReportCollection;
use App\Http\Resources\Moderation\ReportReceiptResource;
use App\Queries\Moderation\ReportQuery;
use App\Services\Moderation\CreateReportService;
use App\Services\Moderation\DecideReportService;
use Illuminate\Http\JsonResponse;

final class ReportController
{
    public function store(CreateReportRequest $request, CreateReportService $service): JsonResponse
    {
        return (new ReportReceiptResource($service->create($request->member(), $request->reportData())))->response()->setStatusCode(201);
    }

    public function index(ReportListRequest $request, ReportQuery $query): ReportCollection
    {
        return new ReportCollection($query->page($request->member(), $request->filters()));
    }

    public function show(ModerationRequest $request, string $id, ReportQuery $query): ModerationReportResource
    {
        return new ModerationReportResource($query->detail($request->member(), $id));
    }

    public function decide(DecideReportRequest $request, string $id, DecideReportService $service): ReportReceiptResource
    {
        return new ReportReceiptResource($service->decide($request->member(), $id, $request->decision()));
    }
}
