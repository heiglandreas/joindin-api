<?php

abstract class ApiController
{
    protected $config;

    public function __construct($config = null)
    {
        $this->config = $config;
    }

    public function getItemId($request)
    {
        // item ID
        if (! empty($request->url_elements[3])
            && is_numeric($request->url_elements[3])
        ) {
            $item_id = (int) $request->url_elements[3];

            return $item_id;
        }

        return false;
    }

    public function getVerbosity($request)
    {
        // verbosity
        if ($request->getParameter('verbose') !== 'yes') {
            return false;
        }

        return true;
    }

    public function getStart($request)
    {
        return $request->paginationParameters['start'];

    }

    public function getResultsPerPage($request)
    {
        return (int) $request->paginationParameters['resultsperpage'];
    }

    public function getSort($request)
    {
        // unfiltered, you probably want to switch case this
        if (isset($request->parameters['sort'])) {
            return $request->parameters['sort'];
        } else {
            return false;
        }
    }
}
