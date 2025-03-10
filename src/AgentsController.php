<?php
require_once __DIR__ . "/../crest/crest.php";

class AgentsController
{
    private $agentDepartmentIds = [5, 78, 77, 442, 443];
    private $designationMap = [
        41340 => 'Management',
        41341 => 'Broker',
    ];

    public function processRequest(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendErrorResponse(405, "Method not allowed");
            return;
        }

        $this->processCollectionRequest();
    }

    private function processCollectionRequest(): void
    {
        $departmentsResponse = CRest::call('department.get');
        if (empty($departmentsResponse['result'])) {
            $this->sendErrorResponse(404, "No departments found");
            return;
        }
        $departments = array_filter($departmentsResponse['result'], function ($department) {
            return $department['ID'] != 444 && $department['ID'] != 77;
        });

        $users = $this->getAllUsers();

        if (empty($users)) {
            $this->sendErrorResponse(404, "No users found");
            return;
        }

        $filteredUsers = [];
        $agents = [];

        foreach ($users as $user) {
            if (!isset($user['UF_USR_1741618074302']) || $user['UF_USR_1741618074302'] ===  null) {
                continue;
            }

            $userData = [
                'id' => $user['ID'],
                'full_name' => implode(' ', array_filter([$user['NAME'], $user['SECOND_NAME'] ?? '', $user['LAST_NAME']])),
                'email' => $user['EMAIL'] ?? '',
                'phone' => !empty($user['WORK_PHONE']) ? $user['WORK_PHONE'] : ($user['PERSONAL_MOBILE'] ?? ''),
                'photo' => !empty($user['UF_WEB_SITES']) ? $user['UF_WEB_SITES'] : ($user['PERSONAL_PHOTO'] ?? ''),
                'position' => $user['WORK_POSITION'] ?? '',
                'designation' => $this->designationMap[(int)$user['UF_USR_1741618074302']] ?? '',
            ];

            if (!empty($userData['phone']) && $userData['phone'][0] !== '+') {
                $userData['phone'] = '+' . $userData['phone'];
            }

            $isAgent = false;
            foreach ($user['UF_DEPARTMENT'] ?? [] as $deptId) {
                if (in_array($deptId, $this->agentDepartmentIds)) {
                    $isAgent = true;
                    break;
                }
            }

            if ($isAgent) {
                $agents[] = $userData;
            } else {
                foreach ($user['UF_DEPARTMENT'] ?? [] as $deptId) {
                    if (!isset($filteredUsers[$deptId])) {
                        $filteredUsers[$deptId] = [];
                    }
                    $filteredUsers[$deptId][] = $userData;
                }
            }
        }

        $responseData = ['agents' => $agents];

        foreach ($departments as $dept) {
            if (isset($filteredUsers[$dept['ID']])) {
                $responseData[$dept['NAME']] = $filteredUsers[$dept['ID']];
            }
        }

        $this->sendXmlResponse($responseData);
    }

    private function sendXmlResponse(array $data): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('response');
        $dom->appendChild($root);

        $this->arrayToDomXml($data, $root, $dom);

        echo $dom->saveXML();
        exit;
    }

    private function arrayToDomXml(array $data, \DOMElement $parent, \DOMDocument $dom): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $parentName = $parent->nodeName;
                $itemName = rtrim($parentName, 's');
                if ($itemName === $parentName) {
                    $itemName = 'item';
                }
                $key = $itemName;
            }

            $key = preg_replace('/[^a-z0-9_-]/i', '_', $key);

            if (is_array($value)) {
                $node = $dom->createElement($key);
                $parent->appendChild($node);
                $this->arrayToDomXml($value, $node, $dom);
            } else {
                $element = $dom->createElement($key);
                $parent->appendChild($element);

                if ($value !== null && $value !== '') {
                    $element->appendChild($dom->createCDATASection((string)$value));
                }
            }
        }
    }

    private function getAllUsers(): array
    {
        $allUsers = [];
        $start = 0;

        do {
            $usersResponse = CRest::call('user.get', [
                'filter' => ['ACTIVE' => true],
                'start' => $start
            ]);

            if (!empty($usersResponse['result'])) {
                $allUsers = array_merge($allUsers, $usersResponse['result']);
                $start += count($usersResponse['result']);
            } else {
                break;
            }
        } while (!empty($usersResponse['result']));

        return $allUsers;
    }

    private function sendErrorResponse(int $code, string $message): void
    {
        header("Content-Type: application/xml; charset=utf-8");
        http_response_code($code);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('response');
        $dom->appendChild($root);

        $errorElement = $dom->createElement('error');
        $root->appendChild($errorElement);

        $errorElement->appendChild($dom->createCDATASection($message));

        echo $dom->saveXML();
        exit;
    }
}
