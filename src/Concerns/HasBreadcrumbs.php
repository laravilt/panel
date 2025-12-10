<?php

namespace Laravilt\Panel\Concerns;

trait HasBreadcrumbs
{
    /**
     * Get the page breadcrumbs.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];

        // Add home/dashboard
        $breadcrumbs[] = [
            'label' => __('laravilt-panel::panel.navigation.dashboard'),
            'url' => $this->getPanel()->url(),
        ];

        // Add cluster if this page belongs to one
        if (method_exists(static::class, 'getCluster') && ($clusterClass = static::getCluster())) {
            if (class_exists($clusterClass)) {
                $clusterLabel = method_exists($clusterClass, 'getNavigationLabel')
                    ? $clusterClass::getNavigationLabel()
                    : (method_exists($clusterClass, 'getClusterTitle')
                        ? $clusterClass::getClusterTitle()
                        : class_basename($clusterClass));

                $clusterUrl = method_exists($clusterClass, 'getUrl')
                    ? $clusterClass::getUrl()
                    : null;

                $breadcrumbs[] = [
                    'label' => $clusterLabel,
                    'url' => $clusterUrl,
                ];
            }
        }
        // Add navigation group if exists (and no cluster)
        elseif ($group = static::getNavigationGroup()) {
            $breadcrumbs[] = [
                'label' => $group,
                'url' => null,
            ];
        }

        // Add resource list page if this is a resource page (Create, Edit, View)
        if (method_exists(static::class, 'getResource')) {
            $resource = static::getResource();
            if ($resource) {
                // Only add the list page breadcrumb if we're NOT on the list page itself
                if (! ($this instanceof \Laravilt\Panel\Pages\ListRecords)) {
                    $breadcrumbs[] = [
                        'label' => $resource::getPluralLabel() ?? $resource::getLabel(),
                        'url' => $resource::getUrl('list'),
                    ];
                }
            }
        }

        // Add current page
        $breadcrumbs[] = [
            'label' => static::getTitle(),
            'url' => null,
        ];

        return $breadcrumbs;
    }
}
