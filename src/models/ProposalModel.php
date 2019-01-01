<?php

/**
 * Object that represents a talk
 */
class ProposalModel extends BaseModel
{
    /**
     * Default fields in the output view
     *
     * format: [public facing name => database column]
     *
     * @return array
     */
    public function getDefaultFields()
    {
        return array(
            'talk_title'              => 'talk_title',
            'url_friendly_talk_title' => 'url_friendly_talk_title',
            'talk_description'        => 'talk_desc',
            'type'                    => 'talk_type',
            'duration'                => 'duration',
            'stub'                    => 'stub',
            'language'                => 'language'
        );
    }

    /**
     * Default fields in the output view
     *
     * format: [public facing name => database column]
     *
     * @return array
     */
    public function getVerboseFields()
    {
        return $this->getDefaultFields();
    }

    /**
     * List of subresource keys that may be in the data set from the mapper
     * but are not database columns that need to be in the output view
     *
     * format: [public facing name => field in $this->data]
     *
     * @return array
     */
    public function getSubResources()
    {
        return [
            'speakers' => 'speakers',
        ];
    }

    /**
     * Return this object with client-facing fields and hypermedia, ready for output
     *
     * @param Request $request
     * @param bool $verbose
     *
     * @return array
     */
    public function getOutputView(Request $request, $verbose = false)
    {
        $item = parent::getOutputView($request, $verbose);
        
        // add Hypermedia
        $base    = $request->base;
        $version = $request->version;

        $item['uri']                  = $base . '/' . $version . '/proposal/' . $this->ID;
        $item['verbose_uri']          = $base . '/' . $version . '/proposal/' . $this->ID . '?verbose=yes';
        $item['website_uri']          = $request->getConfigValue('website_url') . '/proposal/' . $this->stub;
        $item['event_uri']            = $base . '/' . $version . '/events/' . $this->event_id;
        $item['speakers_uri']         = $base . '/' . $version . '/proposal/' . $this->ID . '/speakers';

        return $item;
    }
}
