<?php
/**
 * NdlForwardRecord Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/KDK-Alli/RecordManager
 */
require_once 'ForwardRecord.php';
require_once 'MetadataUtils.php';

/**
 * NdlForwardRecord Class
 *
 * ForwardRecord with NDL specific functionality
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/KDK-Alli/RecordManager
 */
class NdlForwardRecord extends ForwardRecord
{
    /**
     * Default primary author relator codes, may be overridden in configuration.
     *
     * @var array
     */
    protected $primaryAuthorRelators = [
        'A00', 'A01', 'A02', 'A03', 'A05', 'A06', 'A08', 'A09', 'A10', 'A11', 'A12',
        'A13', 'A31', 'A38', 'A43', 'A50', 'A99',
        // Some of these are from MarcRecord
        'adp', 'aud', 'chr', 'cmm', 'cmp', 'cre', 'dub', 'inv'
    ];

    /**
     * Default secondary author relator codes, may be overridden in configuration.
     *
     * @var array
     */
    protected $secondaryAuthorRelators = [
        'D01', 'D02', 'D99', 'E01', 'E02', 'E03', 'E04', 'E05', 'E06', 'E08',
        'F01', 'F02', 'F99', 'ctb', 'exp', 'rce', 'wst', 'sds', 'oth',
        // These are copied from MarcRecord
        'act', 'anm', 'ann', 'arr', 'acp', 'ar', 'ard', 'aft', 'aud', 'aui', 'aus',
        'bjd', 'bpd', 'cll', 'ctg', 'chr', 'cng', 'clb', 'clr', 'cmm', 'cwt', 'com',
        'cpl', 'cpt', 'cpe', 'ccp', 'cnd', 'cos', 'cot', 'coe', 'cts', 'ctt', 'cte',
        'ctb', 'crp', 'cst', 'cov', 'cur', 'dnc', 'dtc', 'dto', 'dfd', 'dft', 'dfe',
        'dln', 'dpc', 'dsr', 'drt', 'dis', 'drm', 'edt', 'elt', 'egr', 'etr', 'fac',
        'fld', 'flm', 'frg', 'ilu', 'ill', 'ins', 'itr', 'ivr', 'ldr', 'lsa', 'led',
        'lil', 'lit', 'lie', 'lel', 'let', 'lee', 'lbt', 'lgd', 'ltg', 'lyr', 'mrb',
        'mte', 'msd', 'mus', 'nrt', 'opn', 'org', 'pta', 'pth', 'prf', 'pht', 'ptf',
        'ptt', 'pte', 'prt', 'pop', 'prm', 'pro', 'pmn', 'prd', 'prg', 'pdr', 'pbd',
        'ppt', 'ren', 'rpt', 'rth', 'rtm', 'res', 'rsp', 'rst', 'rse', 'rpy', 'rsg',
        'rev', 'rbr', 'sce', 'sad', 'scr', 'scl', 'spy', 'std', 'sng', 'sds', 'spk',
        'stm', 'str', 'stl', 'sht', 'ths', 'trl', 'tyd', 'tyg', 'vdg', 'voc', 'wde',
        'wdc', 'wam'
    ];

    /**
     * Default corporate author relator codes, may be overridden in configuration.
     *
     * @var array
     */
    protected $corporateAuthorRelators = [
        'E10', 'dst', 'prn', 'fnd', 'lbr'
    ];

    /**
     * Return fields to be indexed in Solr (an alternative to an XSL transformation)
     *
     * @return string[]
     */
    public function toSolrArray()
    {
        $data = parent::toSolrArray();

        if (isset($data['publishDate'])) {
            $year = MetadataUtils::extractYear($data['publishDate']);
            $data['main_date_str'] = $year;
            $data['main_date'] = $this->validateDate("$year-01-01T00:00:00Z");
            $data['search_daterange_mv'][] = $data['publication_daterange']
                = metadataUtils::dateRangeToStr(
                    ["$year-01-01T00:00:00Z", "$year-12-31T23:59:59Z"]
                );
        }

        $data['publisher'] = $this->getPublishers();
        $data['genre'] = $this->getGenres();

        $data['topic'] = $this->getSubjects();

        $data['source_str_mv'] = $this->source;
        $data['datasource_str_mv'] = $this->source;

        if ($urls = $this->getOnlineUrls()) {
            $data['online_boolean'] = true;
            $data['online_str_mv'] = $this->source;
            foreach ($urls as $url) {
                $data['online_urls_str_mv'][] = json_encode($url);
            }
        }

        return $data;
    }


    /**
     * Return host record ID for component part
     *
     * @return string
     */
    public function getHostRecordID()
    {
        if (!($parentIdType = $this->getDriverParam('parentIdType', ''))) {
            return '';
        }
        foreach ($this->getMainElement()->HasAgent as $agent) {
            if ($agent->AgentIdentifier && $agent->AgentIdentifier->IDTypeName
                && $agent->AgentIdentifier->IDValue
                && (string)$agent->AgentIdentifier->IDTypeName == $parentIdType
            ) {
                return (string)$agent->AgentIdentifier->IDTypeName . '_'
                    . (string)$agent->AgentIdentifier->IDValue;
            }
        }
        return '';
    }

    /**
     * Merge component parts to this record
     *
     * @param MongoCollection $componentParts Component parts to be merged
     *
     * @return int Count of records merged
     */
    public function mergeComponentParts($componentParts)
    {
        $count = 0;
        $parts = [];
        foreach ($componentParts as $componentPart) {
            $data = MetadataUtils::getRecordData($componentPart, true);
            $xml = simplexml_load_string($data);
            foreach ($xml->children() as $child) {
                $parts[] = [
                    'xml' => $child,
                    'order' => empty($child->Title->PartDesignation->Value)
                        ? 0 : (int)$child->Title->PartDesignation->Value
                ];
            }
            ++$count;
        }
        usort(
            $parts,
            function ($a, $b) {
                return $a['order'] - $b['order'];
            }
        );
        foreach ($parts as $part) {
            $this->appendXml($this->doc, $part['xml']);
        }
        return $count;
    }

    /**
     * Recursive function to get fields to be indexed in allfields
     *
     * @param string $fields Fields to use (optional)
     *
     * @return array
     */
    protected function getAllFields($fields = null)
    {
        $results = parent::getAllFields($fields);
        if (null === $fields) {
            $results = array_merge($results, $this->getDescriptions());
            $results = array_merge($results, $this->getContents());
        }
        return $results;
    }

    /**
     * Return publishers
     *
     * @return array
     */
    protected function getPublishers()
    {
        $result = [];
        foreach ($this->getMainElement()->HasAgent as $agent) {
            $attributes = $agent->Activity->attributes();
            if (!empty($attributes->{'elokuva-elotuotantoyhtio'})) {
                $result[] = (string)$agent->AgentName;
            }
        }
        return $result;
    }

    /**
     * Return genres
     *
     * @return array
     */
    protected function getGenres()
    {
        return [$this->getProductionEventAttribute('elokuva-genre')];
    }

    /**
     * Return publication year/date range
     *
     * @return array|null
     */
    protected function getPublicationDateRange()
    {
        $year = $this->getPublicationYear();
        if ($year) {
            $startDate = "$year-01-01T00:00:00Z";
            $endDate = "$year-12-31T23:59:59Z";
            return [$startDate, $endDate];
        }
        return null;
    }

    /**
     * Return a production event attribute
     *
     * @param string $attribute Attribute name
     *
     * @return string
     */
    protected function getProductionEventAttribute($attribute)
    {
        foreach ($this->getMainElement()->ProductionEvent as $event) {
            $attributes = $event->ProductionEventType->attributes();
            if (!empty($attributes{$attribute})) {
                return (string)$attributes{$attribute};
            }
        }
        return '';
    }

    /**
     * Get relator code for the agent
     *
     * @param SimpleXMLElement $agent Agent
     *
     * @return string
     */
    protected function getRelator($agent)
    {
        if (empty($agent->Activity)) {
            return '';
        }
        $activity = $agent->Activity;
        $relator = $this->normalizeRelator((string)$activity);
        if (($relator == 'A99' || $relator == 'E99')
            && !empty($activity->attributes()->{'finna-activity-text'})
        ) {
            $relator = (string)$activity->attributes()->{'finna-activity-text'};
        }
        return $relator;

    }

    /**
     * Get contents
     *
     * @return array
     */
    protected function getContents()
    {
        $results = [];
        foreach ($this->getMainElement()->ProductionEvent as $event) {
            foreach ($event->elokuva_sisaltoseloste as $item) {
                $results[] = (string)$item;
            }
        }
        return $results;
    }

    /**
     * Get all descriptions
     *
     * @return array
     */
    protected function getDescriptions()
    {
        $results = [];
        foreach ($this->getMainElement()->ProductionEvent as $event) {
            foreach ($event->elokuva_tiivistelma as $item) {
                $results[] = (string)$item;
            }
        }
        return $results;
    }

    /**
     * Get all subjects
     *
     * @return array
     */
    protected function getSubjects()
    {
        $results = [];
        foreach ($this->getMainElement()->ProductionEvent as $event) {
            foreach ($event->elokuva_asiasana as $item) {
                $results[] = (string)$item;
            }
        }
        return $results;
    }


    /**
     * Get thumbnail
     *
     * @return string
     */
    protected function getThumbnail()
    {
        foreach ($this->getMainElement()->ProductionEvent as $event) {
            $attributes = $event->ProductionEventType->attributes();
            if ($attributes->{'elokuva-elonet-materiaali-kuva-url'}) {
                return (string)$attributes->{'elokuva-elonet-materiaali-kuva-url'};
            }
        }
        return '';
    }

    /**
     * Get URLs
     *
     * @return array
     */
    protected function getUrls()
    {
        $results = [];
        $records = $this->doc->children();
        $records = reset($records);
        foreach (is_array($records) ? $records : [$records] as $record) {
            foreach ($record->ProductionEvent as $event) {
                $attrs = [
                    'elokuva-elonet-url', 'elokuva-elonet-materiaali-video-url'
                ];
                foreach ($attrs as $attr) {
                    $attributes = $event->ProductionEventType->attributes();
                    if ($attributes->{$attr}) {
                        $results[] = (string)$attributes->{$attr};
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Get online URLs
     *
     * @return array
     */
    protected function getOnlineUrls()
    {
        $results = [];
        $records = $this->doc->children();
        $records = reset($records);
        foreach (is_array($records) ? $records : [$records] as $record) {
            if (isset($record->Title->PartDesignation->Value)) {
                $attributes = $record->Title->PartDesignation->Value->attributes();
                if (empty($attributes{'video-tyyppi'})
                    || $attributes{'video-tyyppi'} != 'elokuva'
                ) {
                    continue;
                }
                foreach ($record->ProductionEvent as $event) {
                    $attributes = $event->ProductionEventType->attributes();
                    $url
                        = (string)$attributes
                            ->{'elokuva-elonet-materiaali-video-url'};
                    $type = '';
                    $description = '';
                    if ($record->Title->PartDesignation->Value) {
                        $attributes = $record->Title->PartDesignation->Value
                            ->attributes();
                        $type = (string)$attributes->{'video-tyyppi'};
                        $description = (string)$attributes->{'video-lisatieto'};
                    }
                    $results[] = [
                        'url' => $url,
                        'text' => $description ? $description : $type,
                        'source' => $this->source
                    ];
                }
            }
        }
        return $results;
    }

    /**
     * Recursively append XML
     *
     * @param SimpleXMLElement $simplexml Node to append to
     * @param SimpleXMLElement $append    Node to be appended
     *
     * @return void
     */
    protected function appendXml(&$simplexml, $append)
    {
        if ($append !== null) {
            $name = $append->getName();
            // addChild doesn't encode & ...
            $data = (string)$append;
            $data = str_replace('&', '&amp;', $data);
            $xml = $simplexml->addChild($name, $data);
            foreach ($append->attributes() as $key => $value) {
                 $xml->addAttribute($key, $value);
            }
            foreach ($append->children() as $child) {
                $this->appendXML($xml, $child);
            }
        }
    }
}
