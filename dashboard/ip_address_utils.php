<?php
/**
 * IP Address Utilities for IPv4 and IPv6 Support
 * Handles proper validation, formatting, and display of IP addresses
 */

class IPAddressUtils {
    
    /**
     * Get the real IP address from various headers
     * Handles proxies, load balancers, and CDNs
     */
    public static function getRealIPAddress() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // RFC 7239
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]); // Get first IP if multiple
                
                if (self::isValidIP($ip)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0'; // Fallback
    }
    
    /**
     * Validate if an IP address is valid (IPv4 or IPv6)
     */
    public static function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Check if IP is IPv4
     */
    public static function isIPv4($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
    
    /**
     * Check if IP is IPv6
     */
    public static function isIPv6($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
    
    /**
     * Check if IP is private/local
     */
    public static function isPrivateIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false &&
               filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Check if IP is reserved/loopback
     */
    public static function isReservedIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false &&
               filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Get IP address type and icon
     */
    public static function getIPInfo($ip) {
        if (empty($ip) || !self::isValidIP($ip)) {
            return [
                'type' => 'Invalid',
                'version' => 'Unknown',
                'scope' => 'Invalid',
                'icon' => '❌',
                'class' => 'text-red-600',
                'description' => 'Invalid IP Address',
                'formatted' => $ip ?: 'Unknown IP'
            ];
        }
        
        $isIPv4 = self::isIPv4($ip);
        $isIPv6 = self::isIPv6($ip);
        $isPrivate = self::isPrivateIP($ip);
        $isReserved = self::isReservedIP($ip);
        
        // Determine scope
        $scope = 'Public';
        $icon = '🌐';
        $class = 'text-green-600';
        $description = 'Public Internet';
        
        if ($isReserved) {
            if ($ip === '127.0.0.1' || $ip === '::1') {
                $scope = 'Loopback';
                $icon = '🔄';
                $class = 'text-blue-600';
                $description = 'Localhost/Loopback';
            } else {
                $scope = 'Reserved';
                $icon = '🔒';
                $class = 'text-gray-600';
                $description = 'Reserved Address';
            }
        } elseif ($isPrivate) {
            $scope = 'Private';
            $icon = '🏠';
            $class = 'text-orange-600';
            $description = 'Private Network';
        }
        
        return [
            'type' => $scope,
            'version' => $isIPv4 ? 'IPv4' : ($isIPv6 ? 'IPv6' : 'Unknown'),
            'scope' => $scope,
            'icon' => $icon,
            'class' => $class,
            'description' => $description,
            'formatted' => self::formatIPAddress($ip)
        ];
    }
    
    /**
     * Format IP address for display
     */
    public static function formatIPAddress($ip) {
        if (empty($ip) || !self::isValidIP($ip)) {
            return $ip ?: 'Unknown IP';
        }
        
        if (self::isIPv6($ip)) {
            // Compress IPv6 address
            $formatted = inet_ntop(inet_pton($ip));
            return $formatted !== false ? $formatted : $ip;
        }
        
        return $ip; // IPv4 doesn't need special formatting
    }
    
    /**
     * Get geographic location info for IP using free geolocation service
     */
    public static function getLocationInfo($ip) {
        // Check if we have a test location set in session (for localhost testing)
        if (isset($_SESSION['test_location']) && (self::isReservedIP($ip) || self::isPrivateIP($ip))) {
            $testLocation = $_SESSION['test_location'];
            return [
                'city' => $testLocation['city'],
                'country' => $testLocation['country'],
                'country_code' => $testLocation['country_code'],
                'region' => $testLocation['region'],
                'latitude' => $testLocation['latitude'],
                'longitude' => $testLocation['longitude'],
                'timezone' => date_default_timezone_get(),
                'isp' => 'Test Location (Manual Override)'
            ];
        }
        
        // Handle local/private IPs
        if (!self::isValidIP($ip) || self::isPrivateIP($ip) || self::isReservedIP($ip)) {
            $localInfo = self::getLocalIPInfo($ip);
            return [
                'city' => $localInfo['city'],
                'country' => $localInfo['country'],
                'country_code' => $localInfo['country_code'],
                'region' => $localInfo['region'],
                'latitude' => null,
                'longitude' => null,
                'timezone' => $localInfo['timezone'],
                'isp' => $localInfo['isp']
            ];
        }
        
        // Try to get real geolocation for public IPs
        try {
            // Using ip-api.com (free, no API key required, 1000 requests/hour)
            $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,lat,lon,timezone,isp,query";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5, // 5 second timeout
                    'user_agent' => 'Mozilla/5.0 (compatible; AuditSystem/1.0)'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'city' => $data['city'] ?? null,
                        'country' => $data['country'] ?? null,
                        'country_code' => $data['countryCode'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                        'isp' => $data['isp'] ?? null
                    ];
                }
            }
        } catch (Exception $e) {
            // Fallback to unknown if API fails
            error_log("IP Geolocation API error: " . $e->getMessage());
        }
        
        // Fallback for failed API calls
        return [
            'city' => null,
            'country' => null,
            'country_code' => null,
            'region' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
            'isp' => null
        ];
    }
    
    /**
     * Get location info for local/private IPs
     */
    private static function getLocalIPInfo($ip) {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return [
                'city' => 'Localhost',
                'country' => 'Local Machine',
                'country_code' => 'LH',
                'region' => 'Loopback',
                'timezone' => date_default_timezone_get(),
                'isp' => 'Local System'
            ];
        }
        
        if (self::isPrivateIP($ip)) {
            return [
                'city' => 'Private Network',
                'country' => 'Local Network',
                'country_code' => 'LN',
                'region' => 'Private Range',
                'timezone' => date_default_timezone_get(),
                'isp' => 'Private Network'
            ];
        }
        
        return [
            'city' => 'Unknown',
            'country' => 'Unknown',
            'country_code' => 'XX',
            'region' => 'Unknown',
            'timezone' => null,
            'isp' => 'Unknown'
        ];
    }
    
    /**
     * Anonymize IP address for privacy compliance
     */
    public static function anonymizeIP($ip) {
        if (self::isIPv4($ip)) {
            // Zero out last octet for IPv4
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                return implode('.', $parts);
            }
        } elseif (self::isIPv6($ip)) {
            // Zero out last 64 bits for IPv6
            $addr = inet_pton($ip);
            if ($addr !== false) {
                // Zero out the last 8 bytes (64 bits)
                for ($i = 8; $i < 16; $i++) {
                    $addr[$i] = "\0";
                }
                return inet_ntop($addr);
            }
        }
        
        return $ip;
    }
    
    /**
     * Check if two IP addresses are in the same subnet
     */
    public static function inSameSubnet($ip1, $ip2, $cidr = 24) {
        if (self::isIPv4($ip1) && self::isIPv4($ip2)) {
            $addr1 = ip2long($ip1);
            $addr2 = ip2long($ip2);
            $mask = -1 << (32 - $cidr);
            
            return ($addr1 & $mask) === ($addr2 & $mask);
        }
        
        // IPv6 subnet comparison is more complex
        // This is a simplified version
        if (self::isIPv6($ip1) && self::isIPv6($ip2)) {
            $bin1 = inet_pton($ip1);
            $bin2 = inet_pton($ip2);
            
            if ($bin1 === false || $bin2 === false) {
                return false;
            }
            
            $bytes = intval($cidr / 8);
            $bits = $cidr % 8;
            
            // Compare full bytes
            if (substr($bin1, 0, $bytes) !== substr($bin2, 0, $bytes)) {
                return false;
            }
            
            // Compare remaining bits
            if ($bits > 0 && $bytes < 16) {
                $mask = 0xFF << (8 - $bits);
                return (ord($bin1[$bytes]) & $mask) === (ord($bin2[$bytes]) & $mask);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get IP address range for analysis
     */
    public static function getIPRange($ip) {
        if (self::isIPv4($ip)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return [
                    'class_a' => $parts[0] . '.0.0.0/8',
                    'class_b' => $parts[0] . '.' . $parts[1] . '.0.0/16',
                    'class_c' => $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24'
                ];
            }
        } elseif (self::isIPv6($ip)) {
            // Simplified IPv6 range
            $compressed = inet_ntop(inet_pton($ip));
            $parts = explode(':', $compressed);
            
            return [
                'prefix_64' => implode(':', array_slice($parts, 0, 4)) . '::/64',
                'prefix_48' => implode(':', array_slice($parts, 0, 3)) . '::/48',
                'prefix_32' => implode(':', array_slice($parts, 0, 2)) . '::/32'
            ];
        }
        
        return [];
    }
}

/**
 * Helper function to get current user's IP
 */
function getCurrentUserIP() {
    return IPAddressUtils::getRealIPAddress();
}

/**
 * Helper function to format IP for display
 */
function formatIPForDisplay($ip) {
    $info = IPAddressUtils::getIPInfo($ip);
    return [
        'ip' => $info['formatted'],
        'version' => $info['version'],
        'type' => $info['type'],
        'icon' => $info['icon'],
        'class' => $info['class'],
        'description' => $info['description']
    ];
}
?>
