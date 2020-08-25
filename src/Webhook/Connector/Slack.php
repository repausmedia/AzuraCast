<?php
namespace App\Webhook\Connector;

use App\Entity\StationWebhook;
use App\Event\SendWebhooks;
use GuzzleHttp\Exception\TransferException;

/**
 * Telegram web hook connector.
 *
 * @package App\Webhook\Connector
 */
class Slack extends AbstractConnector
{
    public const NAME = 'slack';

    public function dispatch(SendWebhooks $event, StationWebhook $webhook): void
    {
        $config = $webhook->getConfig();

        $token = trim($config['token'] ?? '');

        if (empty($token)) {
            $this->logger->error('Webhook ' . self::NAME . ' is missing necessary configuration. Skipping...');
            return;
        }

        $messages = $this->replaceVariables([
            'text' => $config['text'],
        ], $event->getNowPlaying());

        try {
            $api_url = (!empty($config['api'])) ? rtrim($config['api'], '/') : 'https://hooks.slack.com/services';
            $webhook_url = $api_url . '/' . $token;

            $request_params = [
                'data' => [
                    'text' => $messages['text'],
                    'parse_mode' => $config['parse_mode'] ?? 'Markdown' // Markdown or HTML
                ]
            ];

            $response = $this->http_client->request('POST', $webhook_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $request_params,
            ]);

            $this->logger->debug(
                sprintf('Webhook %s returned code %d', self::NAME, $response->getStatusCode()),
                [
                    'request_url' => $webhook_url,
                    'request_params' => $request_params,
                    'response_body' => $response->getBody()->getContents(),
                ]
            );
        } catch (TransferException $e) {
            $this->logger->error(sprintf('Error from webhook %s (%d): %s', self::NAME, $e->getCode(),
                $e->getMessage()));
        }
    }
}
