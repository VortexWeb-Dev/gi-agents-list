<?php
require_once __DIR__ . "/../crest/crest.php";

class AgentsController
{
    private $cacheExpiry = 300;
    private $agentDepartmentIds = [5, 78, 77, 442, 443];

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
        $cacheKey = "agents_list";

        $cachedData = $this->getCache($cacheKey);

        if ($cachedData !== false) {
            $this->sendJsonResponse($cachedData);
            return;
        }

        $departmentsResponse = CRest::call('department.get');
        if (empty($departmentsResponse['result'])) {
            $this->sendErrorResponse(404, "No departments found");
            return;
        }
        $departments = $departmentsResponse['result'];

        $users = $this->getAllUsers();

        if (empty($users)) {
            $this->sendErrorResponse(404, "No users found");
            return;
        }

        $filteredUsers = [];
        $agents = [];

        foreach ($users as $user) {
            $userData = [
                'id' => $user['ID'],
                'full_name' => implode(' ', array_filter([$user['NAME'], $user['SECOND_NAME'] ?? '', $user['LAST_NAME']])),
                'email' => $user['EMAIL'] ?? '',
                'phone' => !empty($user['WORK_PHONE']) ? $user['WORK_PHONE'] : ($user['PERSONAL_MOBILE'] ?? ''),
                'photo' => !empty($user['UF_WEB_SITES']) ? $user['UF_WEB_SITES'] : ($user['PERSONAL_PHOTO'] ?? ''),
                'position' => $user['WORK_POSITION'] ?? '',
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

        $this->setCache($cacheKey, $responseData);
        $this->sendJsonResponse($responseData);
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

    private function getCache(string $key)
    {
        $cacheFile = sys_get_temp_dir() . "/bitrix_" . md5($key) . ".cache";

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheExpiry)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        return false;
    }

    private function setCache(string $key, $data): void
    {
        $cacheFile = sys_get_temp_dir() . "/bitrix_" . md5($key) . ".cache";
        file_put_contents($cacheFile, json_encode($data));
    }

    private function sendJsonResponse($data): void
    {
        header("Content-Type: application/json");
        header("Cache-Control: max-age=300, public");
        echo json_encode($data);
        exit;
    }

    private function sendErrorResponse(int $code, string $message): void
    {
        header("Content-Type: application/json");
        http_response_code($code);
        echo json_encode(["error" => $message]);
        exit;
    }
}
