<?php

namespace Modules\Projects\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\Project;
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

        $contractsByProject = $this->contractsFor($projects->pluck('id')->all());
        $sitesByAccount = $this->sitesFor(
            $projects->pluck('customer_account_id')->filter()->unique()->values()->all()
        );

        foreach ($projects as $project) {
            $contracts = $contractsByProject->get($project->id, collect());
            $sites = $project->customer_account_id
                ? $sitesByAccount->get($project->customer_account_id, collect())
                : collect();

            $contractRows = $contracts->values()->all();
            $siteRows = $sites->values()->all();
            $project->setAttribute('contracts', $contractRows);
            $project->setAttribute('sites', $siteRows);
            if (! $project->relationLoaded('contracts')) {
                $project->setRelation('contracts', collect($contractRows));
            }
            $project->setRelation('sites', collect($siteRows));
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
}
