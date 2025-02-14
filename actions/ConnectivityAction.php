<?php

/**
 * This file is part of RSS-Bridge, a PHP project capable of generating RSS and
 * Atom feeds for websites that don't have one.
 *
 * For the full license information, please view the UNLICENSE file distributed
 * with this source code.
 *
 * @package Core
 * @license http://unlicense.org/ UNLICENSE
 * @link    https://github.com/rss-bridge/rss-bridge
 */

/**
 * Checks if the website for a given bridge is reachable.
 *
 * **Remarks**
 * - This action is only available in debug mode.
 * - Returns the bridge status as Json-formatted string.
 * - Returns an error if the bridge is not whitelisted.
 * - Returns a responsive web page that automatically checks all whitelisted
 * bridges (using JavaScript) if no bridge is specified.
 */
class ConnectivityAction implements ActionInterface
{
    private BridgeFactory $bridgeFactory;

    public function __construct()
    {
        $this->bridgeFactory = new BridgeFactory();
    }

    public function execute(array $request)
    {
        if (!Debug::isEnabled()) {
            throw new \Exception('This action is only available in debug mode!');
        }

        $bridgeName = $request['bridge'] ?? null;
        if (!$bridgeName) {
            return render_template('connectivity.html.php');
        }
        $bridgeClassName = $this->bridgeFactory->createBridgeClassName($bridgeName);
        if (!$bridgeClassName) {
            throw new \Exception(sprintf('Bridge not found: %s', $bridgeName));
        }
        return $this->reportBridgeConnectivity($bridgeClassName);
    }

    private function reportBridgeConnectivity($bridgeClassName)
    {
        if (!$this->bridgeFactory->isEnabled($bridgeClassName)) {
            throw new \Exception('Bridge is not whitelisted!');
        }

        $retVal = [
            'bridge' => $bridgeClassName,
            'successful' => false,
            'http_code' => 200,
        ];

        $bridge = $this->bridgeFactory->create($bridgeClassName);
        $curl_opts = [
            CURLOPT_CONNECTTIMEOUT => 5
        ];
        try {
            $reply = getContents($bridge::URI, [], $curl_opts, true);

            if ($reply['code'] === 200) {
                $retVal['successful'] = true;
                if (strpos(implode('', $reply['status_lines']), '301 Moved Permanently')) {
                    $retVal['http_code'] = 301;
                }
            }
        } catch (\Exception $e) {
            $retVal['successful'] = false;
        }

        return new Response(Json::encode($retVal), 200, ['Content-Type' => 'text/json']);
    }
}
