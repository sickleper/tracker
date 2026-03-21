<?php
/**
 * Google Drive Service
 * Handles all Google Drive operations
 */

require_once __DIR__ . '/GoogleClientService.php';

class GoogleDriveService {
    
    private $conn;
    
    public function __construct($conn = null) {
        $this->conn = $conn;
    }
    
    /**
     * Upload files to Google Drive
     * 
     * @param string $poNumber PO number for folder organization
     * @param array $files Files array from $_FILES
     * @param bool $overwrite Whether to overwrite existing files
     * @return int Number of files uploaded
     * @throws Exception
     */
    public function uploadFiles($poNumber, $files, $overwrite = false) {
        $parentFolderId = $_ENV['GALLERY_FOLDER_ID'] ?? getenv('GALLERY_FOLDER_ID') ?? '0AA6L3ZTMVIMwUk9PVA';
        
        try {
            $client = GoogleClientService::getClient([Google_Service_Drive::DRIVE]);
            $service = new Google_Service_Drive($client);

            // 1. Find or Create PO Folder
            $cleanPo = trim($poNumber);
            $cleanPo = str_replace("'", "'", $cleanPo); // Corrected: escaped single quote to be literal single quote
            $query = "name = '$cleanPo' and mimeType = 'application/vnd.google-apps.folder' and '$parentFolderId' in parents and trashed = false";
            $results = $service->files->listFiles([
                'q' => $query, 
                'fields' => 'files(id, name)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true
            ]);
            
            $foundFiles = $results->getFiles();
            if (count($foundFiles) > 0) {
                $folderId = $foundFiles[0]->id;
            } else {
                $fileMetadata = new Google_Service_Drive_DriveFile([
                    'name' => trim($poNumber),
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents' => [$parentFolderId]
                ]);
                $folder = $service->files->create($fileMetadata, [
                    'fields' => 'id',
                    'supportsAllDrives' => true
                ]);
                $folderId = $folder->id;
            }

            // 2. Upload Files
            $uploadedCount = 0;
            foreach ($files['tmp_name'] as $key => $tmpName) {
                if ($files['error'][$key] == UPLOAD_ERR_OK) {
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                    if (!in_array($files['type'][$key], $allowedTypes)) {
                        error_log("Skipping unsupported file type: " . $files['type'][$key]);
                        continue;
                    }
                    
                    $name = basename($files['name'][ $key]);
                    $content = file_get_contents($tmpName);
                    $mimeType = $files['type'][$key];

                    $existingFileId = null;
                    if ($overwrite) {
                        $escapedName = str_replace("'", "'", $name); // Corrected: escaped single quote to be literal single quote
                        $fileQuery = "name = '$escapedName' and '$folderId' in parents and trashed = false";
                        $fileResults = $service->files->listFiles([
                            'q' => $fileQuery,
                            'fields' => 'files(id)',
                            'supportsAllDrives' => true,
                            'includeItemsFromAllDrives' => true
                        ]);
                        $foundItems = $fileResults->getFiles();
                        if (count($foundItems) > 0) {
                            $existingFileId = $foundItems[0]->id;
                        }
                    }

                    if ($existingFileId) {
                        // UPDATE existing file content
                        $service->files->update($existingFileId, new Google_Service_Drive_DriveFile(), [
                            'data' => $content,
                            'mimeType' => $mimeType,
                            'uploadType' => 'multipart',
                            'supportsAllDrives' => true
                        ]);
                    } else {
                        // CREATE new file
                        $fileMetadata = new Google_Service_Drive_DriveFile([
                            'name' => $name,
                            'parents' => [$folderId]
                        ]);
                        $driveFile = $service->files->create($fileMetadata, [
                            'data' => $content,
                            'mimeType' => $mimeType,
                            'uploadType' => 'multipart',
                            'fields' => 'id',
                            'supportsAllDrives' => true
                        ]);

                        // 3. Log to UploadedDocuments table (Only for NEW files)
                        $stmt = $this->conn->prepare("INSERT INTO UploadedDocuments (DocumentName, ProjectName, Category, FilePath, UserID, UploadedDate) VALUES (?, ?, 'Work Order', ?, ?, ?)");
                        $userId = $_SESSION['user_id'] ?? 0;
                        $today = date('Y-m-d');
                        $drivePath = "DRIVE:" . $driveFile->id;
                        $stmt->bind_param("sssis", $name, $poNumber, $drivePath, $userId, $today);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    $uploadedCount++;
                }
            }
            return $uploadedCount;
        } catch (Exception $e) {
            error_log("Google Drive Upload Error (PO: $poNumber): " . $e->getMessage());
            throw new Exception("File upload failed. Please try again.");
        }
    }
    
    /**
     * Delete file from Google Drive
     * 
     * @param string $fileId Google Drive file ID
     * @return bool
     */
    public function deleteFile($fileId) {
        try {
            $client = GoogleClientService::getClient([Google_Service_Drive::DRIVE]);
            $service = new Google_Service_Drive($client);
            $service->files->delete($fileId);
            return true;
        } catch (Exception $e) {
            error_log("Google Drive Delete Error (ID: $fileId): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get attachments for a PO number
     * 
     * @param string $po PO number
     * @return array
     */
    public function getAttachments($po) {
        $parentFolderId = $_ENV['GALLERY_FOLDER_ID'] ?? getenv('GALLERY_FOLDER_ID') ?? '0AA6L3ZTMVIMwUk9PVA';
        $localStorageUploadPath = $_ENV['LOCAL_STORAGE_UPLOAD_PATH'] ?? getenv('LOCAL_STORAGE_UPLOAD_PATH'); // Get local path from .env
        error_log("DEBUG: GDS - getAttachments called. LOCAL_STORAGE_UPLOAD_PATH (GDS): {" . $localStorageUploadPath . "}"); // Added logging

        $files = []; // Initialize empty array for files

        // Try Google Drive first
        if (!empty($parentFolderId)) { // Only try if GALLERY_FOLDER_ID is set
            try {
                $client = GoogleClientService::getClient([Google_Service_Drive::DRIVE]);
                $service = new Google_Service_Drive($client);

                // Find the PO Folder
                $escapedPo = str_replace("'", "'", $po); // Corrected: escaped single quote to be literal single quote
                $query = "name = '$escapedPo' and mimeType = 'application/vnd.google-apps.spreadsheet' and trashed = false and '$parentFolderId' in parents";
                $results = $service->files->listFiles([
                    'q' => $query, 
                    'fields' => 'files(id, name)',
                    'supportsAllDrives' => true,
                    'includeItemsFromAllDrives' => true
                ]);
                
                $foundFolders = $results->getFiles();
                
                if (count($foundFolders) > 0) {
                    $folderId = $foundFolders[0]->id;

                    // List all files in that folder
                    $fileResults = $service->files->listFiles([
                        'q' => "'$folderId' in parents and trashed = false",
                        'fields' => 'files(id, name, createdTime, webViewLink)',
                        'supportsAllDrives' => true,
                        'includeItemsFromAllDrives' => true
                    ]);

                    foreach ($fileResults->getFiles() as $file) {
                        $files[] = [
                            'DocumentName' => $file->name,
                            'FilePath' => $file->webViewLink,
                            'UploadedDate' => date('Y-m-d', strtotime($file->createdTime)),
                            'isDrive' => true
                        ];
                    }
                }
                return ['success' => true, 'files' => $files]; // Return Google Drive results if successful
            } catch (Exception $e) {
                error_log("Google Drive getAttachments error: " . $e->getMessage());
                // Fallback to local storage if Google Drive API fails
            }
        }

        // Fallback to local storage
        if (!empty($localStorageUploadPath)) {
            error_log("DEBUG: GDS - Local Storage Fallback Activated. LOCAL_STORAGE_UPLOAD_PATH: {" . $localStorageUploadPath . "}");
            $taskUploadPath = rtrim($localStorageUploadPath, '/') . '/' . $po;
            error_log("DEBUG: GDS - Checking local path: {" . $taskUploadPath . "}");

            if (is_dir($taskUploadPath)) {
                $localFiles = array_diff(scandir($taskUploadPath), ['.', '..']);
                error_log("DEBUG: GDS - scandir() result: " . json_encode($localFiles));
                foreach ($localFiles as $fileName) {
                    $filePath = $taskUploadPath . '/' . $fileName;
                    if (is_file($filePath)) { // Ensure it's a file, not a subdirectory
                        $files[] = [
                            'DocumentName' => $fileName,
                            'FilePath' => 'file://' . $filePath, // Indicate local file path
                            'UploadedDate' => date('Y-m-d', filectime($filePath)),
                            'isDrive' => false
                        ];
                    }
                }
                error_log("DEBUG: GDS - Found " . count($files) . " local files for PO: {" . $po . "}.");
            } else {
                error_log("DEBUG: GDS - Local Storage Fallback: Directory '{$taskUploadPath}' does not exist or is not a directory for PO: {$po}.");
            }
        }

        return ['success' => true, 'files' => $files];
    }
}