<?php
/**
 * Google Client Service
 * Handles Google API authentication and client creation
 */

class GoogleClientService {
    
    /**
     * Get authenticated Google Client
     * 
     * @param array $scopes Array of Google API scopes
     * @return Google\Client
     * @throws Exception
     */
    public static function getClient($scopes = []) {
        $credentialsPath = '/var/www/html/project-management/cred.json';
        if (!file_exists($credentialsPath)) {
            $credentialsPath = $_ENV['GOOGLE_DRIVE_CREDENTIALS_PATH'] ?? getenv('GOOGLE_DRIVE_CREDENTIALS_PATH') ?? '';
            $credentialsPath = trim($credentialsPath, " \t\n\r\0\x0B\"");
        }

        if (empty($credentialsPath) || !file_exists($credentialsPath)) {
            throw new Exception("Google credentials file not found");
        }

        $client = new Google\Client();
        $client->setAuthConfig($credentialsPath);
        foreach ($scopes as $scope) {
            $client->addScope($scope);
        }
        $client->fetchAccessTokenWithAssertion();
        
        return $client;
    }
}
