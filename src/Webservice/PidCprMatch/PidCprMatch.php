<?php

namespace Nodes\NemId\PidCprMatch;

use GuzzleHttp\Client;
use Nodes\Exception\Exception;
use Nodes\NemId\PidCprMatch\Responses\Response;

/**
 * Class PidCprMatch
 *
 * @author  Casper Rasmussen <cr@nodes.dk>
 * @package Nodes\NemId\PidCprMatch
 */
class PidCprMatch
{

    /**
     * @var \Nodes\NemId\PidCprMatch\Settings
     */
    protected $settings;

    /**
     * PidCprMatch constructor.
     *
     * @param \Nodes\NemId\Model\Mode|null $mode
     */
    public function __construct($mode = null)
    {
        $this->settings = new Settings($mode);
    }

    /**
     * @author Casper Rasmussen <cr@nodes.dk>
     * @param $pid
     * @param $cpr
     * @return \Nodes\NemId\PidCprMatch\Responses\Response
     * @throws \Exception
     */
    public function pidCprRequest($pid, $cpr)
    {
        // Generate xml document
        $pidCprRequest =
            '<?xml version="1.0" encoding="iso-8859-1"?><method name="pidCprRequest" version="1.0"><request><serviceId>x</serviceId><pid>x</pid><cpr>x</cpr></request></method>';

        $document = new \DOMDocument();
        $document->loadXML($pidCprRequest);
        $xp = new \DomXPath($document);

        $pidCprRequestParams = [
            'serviceId' => $this->settings->getServiceId(),
            'pid' => $pid,
            'cpr' => $cpr,
        ];


        $element = $xp->query('/method/request')
            ->item(0);
        $element->setAttribute("id", uniqid());

        foreach ((array)$pidCprRequestParams as $p => $v) {
            $element = $xp->query('/method/request/' . $p)
                ->item(0);
            $newelement = $document->createTextNode($v);
            $element->replaceChild($newelement, $element->firstChild);
        }

        $pidCprRequest = $document->saveXML();

        // Check that certificate exists
        if (!file_exists($this->settings->getCertificateAndKey())) {
            throw new \Exception('Certificate was not found');
        }

        // Init guzzle client
        $client = new Client();

        try {
            // Build params
            $params = [
                'cert' => [
                    $this->settings->getCertificateAndKey(),
                    $this->settings->getPrivateKeyPassword()
                ],
                'form_params' => [
                    'PID_REQUEST' => $pidCprRequest
                ],
                'connect_timeout' => 10,
            ];

            // Set proxy
            if($this->settings->hasProxy()) {
                $params['proxy'] = $this->settings->getProxy();
            }

            // Execute request
            $response = $client->request('POST', $this->settings->getServer(), $params);

            // Parse status code
            $document->loadXML($response->getBody()->getContents());
            $xp = new \DomXPath($document);
            $status = intval($xp->query('/method/response/status/@statusCode')->item(0)->value);

            return new Response($status);
        } catch (\Exception $e) {
            throw new \Exception($e->getTraceAsString());
//            return new Response(-1, $e);
        }

    }
}