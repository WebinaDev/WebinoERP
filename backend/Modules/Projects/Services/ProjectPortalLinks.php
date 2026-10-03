<?php

namespace Modules\Projects\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjAppointment;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\ProInvoice;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class ProjectPortalLinks
{
    /**
     * Attach contract rows and site-builder entries that belong to each project.
     *
     * @param  iterable<int, Project>  $projects
     */
    public function attach(iterable $projects): void
    {
        $projects = collect($projects);
        if ($projects->isEmpty()) {
            return;
        }

        $projectIds = $projects->pluck('id')->all();
        $accountIds = $projects->pluck('customer_account_id')->filter()->unique()->values()->all();
        $contractsByProject = $this->contractsFor($projectIds);
        $sitesByAccount = $this->sitesFor($accountIds);
        $invoicesByProject = $this->invoicesFor($projectIds);
        $ticketsByProject = $this->ticketsFor($projectIds);
        $appointmentsByAccount = $this->appointmentsFor($accountIds);

        foreach ($projects as $project) {
            $contracts = $contractsByProject->get($project->id, collect());
            $sites = $project->customer_account_id
                ? $sitesByAccount->get($project->customer_account_id, collect())
                : collect();
            $appointments = $project->customer_account_id
                ? $appointmentsByAccount->get($project->customer_account_id, collect())
                : collect();

            $contractRows = $contracts->values()->all();
            $siteRows = $sites->values()->all();
            $invoiceRows = $invoicesByProject->get($project->id, collect())->values()->all();
            $appointmentRows = $appointments->values()->all();
            $project->setAttribute('contracts', $contractRows);
            $project->setAttribute('sites', $siteRows);
            $project->setAttribute('invoices', $invoiceRows);
            $project->setAttribute('appointments', $appointmentRows);
            if (! $project->relationLoaded('contracts')) {
                $project->setRelation('contracts', collect($contractRows));
            }
            if (! $project->relationLoaded('tickets')) {
                $ticketRows = $ticketsByProject->get($project->id, collect())->values()->all();
                $project->setAttribute('tickets', $ticketRows);
                $project->setRelation('tickets', collect($ticketRows));
            }
            $project->setRelation('sites', collect($siteRows));
            $project->setRelation('invoices', collect($invoiceRows));
            $project->setRelation('appointments', collect($appointmentRows));
        }
    }

    /**
     * @param  list<int>  $projectIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function contractsFor(array $projectIds): Collection
    {
        if ($projectIds === [] || ! Schema::hasTable('prj_contracts')) {
            return collect();
        }

        return Contract::query()
            ->whereIn('project_id', $projectIds)
            ->orderByDesc('id')
            ->get(['id', 'project_id', 'title', 'status', 'amount'])
            ->groupBy('project_id')
            ->map(fn (Collection $rows) => $rows->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'title' => $contract->title,
                'status' => $contract->status,
                'amount' => $contract->amount,
            ]));
    }

    /**
     * @param  list<int>  $accountIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function sitesFor(array $accountIds): Collection
    {
        if ($accountIds === [] || ! Schema::hasTable('webino_site_provisions')) {
            return collect();
        }

        return WebinoSiteProvision::query()
            ->whereIn('crm_account_id', $accountIds)
            ->orderByDesc('id')
            ->get(['id', 'crm_account_id', 'domain', 'slug', 'status'])
            ->groupBy('crm_account_id')
            ->map(fn (Collection $rows) => $rows->map(fn (WebinoSiteProvision $site) => [
                'id' => $site->id,
                'domain' => $site->domain,
                'slug' => $site->slug,
                'status' => $site->status,
                'builder_path' => 'admin/platform/sites/'.$site->id,
            ]));
    }

    /**
     * @param  list<int>  $projectIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function invoicesFor(array $projectIds): Collection
    {
        if ($projectIds === []) {
            return collect();
        }

        $rows = collect();
        if (Schema::hasTable('prj_pro_invoices')) {
            $rows = $rows->concat(
                ProInvoice::query()
                    ->whereIn('project_id', $projectIds)
                    ->orderByDesc('id')
                    ->get(['id', 'project_id', 'number', 'status', 'total'])
                    ->map(fn (ProInvoice $invoice) => [
                        'id' => $invoice->id,
                        'project_id' => $invoice->project_id,
                        'number' => $invoice->number,
                        'status' => $invoice->status,
                        'total' => $invoice->total,
                        'path' => 'pm/invoices?project_id='.$invoice->project_id.'&invoice_id='.$invoice->id,
                    ])
            );
        }
        if (Schema::hasTable('sales_invoices')) {
            $rows = $rows->concat(
                SalesInvoice::query()
                    ->whereIn('project_id', $projectIds)
                    ->orderByDesc('id')
                    ->get(['id', 'project_id', 'number', 'status', 'total'])
                    ->map(fn (SalesInvoice $invoice) => [
                        'id' => $invoice->id,
                        'project_id' => $invoice->project_id,
                        'number' => $invoice->number,
                        'status' => $invoice->status,
                        'total' => $invoice->total,
                        'path' => 'sales/invoices?invoice_id='.$invoice->id,
                    ])
            );
        }

        return $rows->groupBy('project_id');
    }

    /**
     * @param  list<int>  $projectIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function ticketsFor(array $projectIds): Collection
    {
        if ($projectIds === [] || ! Schema::hasTable('prj_tickets')) {
            return collect();
        }

        return PrjTicket::query()
            ->whereIn('project_id', $projectIds)
            ->orderByDesc('id')
            ->get(['id', 'project_id', 'subject', 'status', 'created_at'])
            ->groupBy('project_id')
            ->map(fn (Collection $rows) => $rows->map(fn (PrjTicket $ticket) => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
                'path' => 'crm/tickets?ticket_id='.$ticket->id,
            ]));
    }

    /**
     * @param  list<int>  $accountIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function appointmentsFor(array $accountIds): Collection
    {
        if ($accountIds === [] || ! Schema::hasTable('prj_appointments')) {
            return collect();
        }

        return PrjAppointment::query()
            ->whereIn('customer_account_id', $accountIds)
            ->orderByDesc('starts_at')
            ->get(['id', 'customer_account_id', 'title', 'status', 'starts_at'])
            ->groupBy('customer_account_id')
            ->map(fn (Collection $rows) => $rows->map(fn (PrjAppointment $appointment) => [
                'id' => $appointment->id,
                'title' => $appointment->title,
                'status' => $appointment->status,
                'starts_at' => $appointment->starts_at,
                'path' => 'pm/appointments?appointment_id='.$appointment->id,
            ]));
    }
}
