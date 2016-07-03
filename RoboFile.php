<?php

use GuzzleHttp\Client;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    const DATE_FORMAT = 'Y-m-d\TH:i:sP';
    const API_KEY_FILENAME = 'apiKey';
    const INIT_DATA_FILENAME = 'init';

    private $token;

    /**
     * @return Client
     */
    private function getClient()
    {
        if (!file_exists(self::INIT_DATA_FILENAME)) {
            throw new Exception('Initialize project first');
        }

        $this->token = file_get_contents(self::API_KEY_FILENAME);
        return new Client(['base_uri' => 'https://www.toggl.com']);
    }

    private function getRequest($url)
    {
        return $this->getClient()->get($url, [
            'auth' => [$this->token, 'api_token']
        ]);
    }

    /**
     * Register the time you started working
     */
    public function init()
    {
        if (!file_exists(self::API_KEY_FILENAME)) {
            $apiKey = $this->ask('Your Toggl API key is: ');
            file_put_contents(self::API_KEY_FILENAME, $apiKey);
        }

        $dt = (new DateTime())->format(self::DATE_FORMAT);
        file_put_contents(self::INIT_DATA_FILENAME, $dt);
        $this->say($dt);
    }

    /**
     * Returns tasks started today
     */
    public function run($when = 'today')
    {
        $dtStart = (new DateTime($when))->setTime(0, 0, 0);
        $dtEnd = (new DateTime($when))->setTime(23, 59, 59);

        $response = $this->getRequest('api/v8/time_entries?' . http_build_query([
                'start_date' => $dtStart->format(self::DATE_FORMAT),
                'end_date'   => $dtEnd->format(self::DATE_FORMAT),
            ]));

        $data = json_decode($response->getBody()->getContents(), true);

        if (!file_exists(self::INIT_DATA_FILENAME)) {
            throw new Exception('You havent registered start time!');
        }

        $start = new DateTime(file_get_contents(self::INIT_DATA_FILENAME));
        $end = new DateTime();

        $desiredSumInSeconds = $end->getTimestamp() - $start->getTimestamp();

        $measuredSum = 0;
        foreach ($data as $entry) {
            $measuredSum += $entry['duration'];
        }

        $differenceInSeconds = $desiredSumInSeconds - $measuredSum;
        $overflow = false;
        if ($differenceInSeconds < 0) {
            $differenceInSeconds = $measuredSum - $desiredSumInSeconds;
            $overflow = true;
        }

        $readableDateTimeFormat = 'd.m.Y. H:i:s';
        $this->say(sprintf('You started working at: %s', $start->format($readableDateTimeFormat)));
        $this->say(sprintf('You ended working at: %s', $end->format($readableDateTimeFormat)));
        $this->say(sprintf('You logged: %s', gmdate("H:i:s", $measuredSum)));

        if (!$overflow) {
            $this->yell(sprintf('The difference is: %s', gmdate("H:i:s", $differenceInSeconds)));
        } else {
            $this->say('');
            $this->say('You logged more than actual time passed!');
            $this->yell(sprintf('The difference is: -%s', gmdate("H:i:s", $differenceInSeconds)), 40, 'red');
        }
    }
}