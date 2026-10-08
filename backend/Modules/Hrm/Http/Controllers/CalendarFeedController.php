<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Hrm\Entities\HrmCalendarFeed;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Services\HrmCalendarFeedService;
use Modules\Hrm\Support\HrmAccess;

class CalendarFeedController extends Controller
{
    /** Portal: download own ICS. */
    public function mine(Request $request, HrmCalendarFeedService $feeds): Response
    {
        $employee = HrmAccess::employee();
        abort_unless($employee, 404, 'No employee profile');

        return $this->icsResponse($feeds->ics($employee, (string) $request->input('lang', 'fa')), 'my-hr.ics', true);
    }

    /** Portal: subscription links (creates the token on first call). */
    public function ensure(HrmCalendarFeedService $feeds): JsonResponse
    {
        $employee = HrmAccess::employee();
        abort_unless($employee, 404, 'No employee profile');

        return response()->json(['data' => $feeds->links($feeds->feedFor($employee))]);
    }

    /** Portal: new token; the previous link stops working. */
    public function regenerate(HrmCalendarFeedService $feeds): JsonResponse
    {
        $employee = HrmAccess::employee();
        abort_unless($employee, 404, 'No employee profile');

        return response()->json(['data' => $feeds->links($feeds->regenerate($employee))]);
    }

    /** HR: download an employee's ICS. */
    public function employeeIcs(Request $request, HrmEmployee $employee, HrmCalendarFeedService $feeds): Response
    {
        if (! HrmAccess::seesAllStaff()) {
            abort_unless(HrmAccess::employee()?->id === $employee->id, 403);
        }

        return $this->icsResponse($feeds->ics($employee, (string) $request->input('lang', 'fa')), 'employee-'.$employee->id.'.ics', true);
    }

    /** Public, token-authenticated feed for calendar apps. */
    public function publicFeed(Request $request, string $token, HrmCalendarFeedService $feeds): Response
    {
        $token = preg_replace('/\.ics$/', '', $token) ?? $token;
        $feed = HrmCalendarFeed::query()->where('token', $token)->first();
        abort_unless($feed && $feed->employee, 404);

        return $this->icsResponse($feeds->ics($feed->employee, (string) $request->input('lang', 'fa')), 'webino-hr.ics');
    }

    private function icsResponse(string $body, string $filename, bool $download = false): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
