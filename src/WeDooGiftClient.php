<?php

namespace Wassa\WeDooGift;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

class WeDooGiftClient
{
  const BASE_URL = 'https://api-v3-demo.wedoogift.com/api/v3';

  /** @var string **/
  private $apiKey;
  /** @var Client */
  private $client;
  /** @var int */
  private $companyId;

  public function __construct(string $apiKey) {
    $this->apiKey = $apiKey;
    $this->client = new Client();
  }

  /**
   * Init the client. Must be called before anything else.
   * @throws \GuzzleHttp\Exception\TransferException
   */
  public function init() {
    $current = $this->sendRequest('GET', '/current');
    $this->companyId = $current->createdByCompany->id;
  }

  /**
   * Add a user to the company. The use must not already exist.
   * @param string $firstName
   * @param string $lastName
   * @param string $email
   * @param string $locale
   * @return int
   * @throws \GuzzleHttp\Exception\TransferException
   * @throws WeDooGiftException
   */
  public function addUser(string $firstName, string $lastName, string $email,  string $locale= 'fr_FR'): int {
    try {
      $user = $this->sendRequest('POST', "/company/$this->companyId/user", [
        'json' => [
          'firstName' => $firstName,
          'lastName' => $lastName,
          'email' => $email,
          'login' => $email,
          'locale' => $locale,
        ]
      ]);
      $userId = $user->id;

      return $userId;
    } catch (TransferException $e) {
     throw new WeDooGiftException("Unable to add user: {$e->getMessage()}");
    }
  }

  /**
   * Make a distribution to a user
   * @param int $reasonId
   * @param string $message
   * @param int $userId
   * @param int $value
   * @param string $currency
   * @throws \GuzzleHttp\Exception\TransferException
   */
  public function distribute(int $reasonId, string $message, int $userId, int $value, string $currency = 'EUR') {
    // First list all deposits that have sufficient balance
    $deposits = $this->listDeposits($value);
    if (count($deposits) == 0) {
      throw new WeDooGiftException('Unable to distribute: no deposit with sufficient balance');
    }

    // We use the first available deposit
    $depositId = $deposits[0]->id;

    try {
      $task = $this->sendRequest('POST', "/company/$this->companyId/distribution", [
        'json' => [
          'reasonId' => $reasonId,
          'depositId' => $depositId,
          "startDate" => (new \DateTime())->format(DATE_ATOM),
          'message' => $message,
          'beneficiaries' => [
            ['userId' => $userId,
              'amount' => [
                'value' => "$value",
                'currency' => $currency
              ]]
          ]
        ]
      ]);
    } catch (TransferException $e) {
      throw new WeDooGiftException("Unable to distribute: {$e->getMessage()}");
    }

    if ($task->status != 'STARTING') {
      throw new WeDooGiftException("Unable to distribute: task status is not STARTING");
    }
  }

  /**
   * List all available deposits
   * @param int|null $value
   * @return array
   * @throws WeDooGiftException
   */
  public function listDeposits(int $value = null): array
  {
    try {
      $res = $this->sendRequest('GET', "/company/$this->companyId/deposit?page=0&size=1000000000");
    } catch (TransferException $e) {
      throw new WeDooGiftException("Unable to list deposits: {$e->getMessage()}");
    }

    $content = $res->content;
    if (!is_array($content)) {
      throw new WeDooGiftException("Unable to list deposits: bad response from WeDooGift API");
    }

    $deposits = [];

    foreach ($content as $deposit) {
      if (isset($deposit->balance)) {
        if (!$value || $value <= intval($deposit->balance->value)) {
          array_push($deposits, $deposit);
        }
      }
    }

    return $deposits;
  }

  /**
   * @param string $method
   * @param string $uri
   * @param array $options
   * @return mixed
   * @throws \GuzzleHttp\Exception\TransferException
   */
  private function sendRequest(string $method, string $uri = '', array $options = []) {
    $options = array_merge_recursive($options, [
      'headers' => [
        'Authorization' => $this->apiKey
      ]
    ]);

    $req = $this->client->createRequest($method, self::BASE_URL . $uri, $options);
    $res = $this->client->send($req);

    return json_decode($res->getBody()->getContents());
  }
}
