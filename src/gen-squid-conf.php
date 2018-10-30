<?php

# Protection for XSS atack
header('Content-Type:text/plain');

function set_default_variable($variable, $default_value) {
    # If the value of the variable contains only a to z , A to Z, 0 to 9 and _ (underscore), use it instead of the default value.
    if (preg_match('/^[\w\.]+$/', $variable)) {
        return $variable;
    } else {
        return $default_value;
    }
}

function get_request() {
    $content_type = explode(';', trim(strtolower($_SERVER['CONTENT_TYPE'])));
    $media_type   = $content_type[0];

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $media_type == 'application/json') {
        $request = json_decode(file_get_contents('php://input'), true);
    } else {
        exit("Usage: Content-Type should be 'application/json'. HTTP Method should be 'POST'.");
    }
    return $request;
}

# Set a default region and environment
$region      = set_default_variable($_GET['region'], 'eu');
$environment = set_default_variable($_GET['environment'], 'production');

# Get an json data
$result = get_request();

# Extract an allowed_ips and allowed_domains.
$allowed_ips     = $result[$region][$environment]['egress']['allowed_ips'];
$allowed_domains = $result[$region][$environment]['egress']['allowed_domains'];

# Sort the array
sort($allowed_ips);
sort($allowed_domains);

# Concat ports of duplicate ips.
$uniq_ips    = [];
$uniq_ports  = [];
$array_stack = [];

$len = count($allowed_ips);
$i   = 0;

# If there are more than two elements in the array, start to check the duplicate settings.
if ($len > 0) {
    foreach ($allowed_ips as $allowed_ip) {
        # [0] -> ip, [1] -> port
        $array_ip_port = explode(":", $allowed_ip);
        $ip            = $array_ip_port[0];
        $port          = $array_ip_port[1];

        # Count up the iterator
        $i++;

        # Skip to compare if it's the first or last element.
        if ($i == 1) {
            # First element
            $previous_ip   = $ip;
            $previous_port = $port;
            continue;
        }

        # Compare current ip and port to previous ones.
        array_push($array_stack, $previous_port);
        if ($previous_ip != $ip) {
            # Remove duplicate values from array_stack.
            $uniq_array_stack = array_unique($array_stack);
            array_push($uniq_ips,  $previous_ip . ":" . join(" ", $uniq_array_stack));
            $array_stack   = [];
        }

        $previous_ip   = $ip;
        $previous_port = $port;

         if ($i == $len) {
            # Last element
            array_push($array_stack, $port);
            # Remove duplicate values from array_stack.
            $uniq_array_stack = array_unique($array_stack);
            array_push($uniq_ips,  $ip . ":" . join(" ", $uniq_array_stack));
        }
    }
}

# Debug information
$debug = $_GET['debug'];
if ($debug == "on") {
    print "##########\n";
    print "Debug Mode\n";
    print "##########\n";
    print "1. Region and Environment\n";
    print "Region: Set as " . $region . "\n";
    print "Environment: Set as " . $environment . "\n";
    print "2. Elements of allowed ips (removed duplicates)\n";
    print_r($uniq_ips);
    print "3. Elements of allowed domains\n";
    print_r($allowed_domains);
}

# Output the template for squid
include("squid.template");

# Output for allowed ips
foreach ($uniq_ips as $index=>$allowed_ip) {
    # [0] -> ip, [1] -> port
    $array_ip_port = explode(":", $allowed_ip);
    $ip            = $array_ip_port[0];
    $port          = $array_ip_port[1];
    echo "acl service_" . $index . "_ip dstdomain " . $ip . "\n";
    echo "acl service_" . $index . "_ip_port port " . $port . "\n";
    echo "http_access allow allowed_methods service_" . $index . "_ip_port service_" . $index . "_ip\n";
    echo "\n";
}

# Output for allowed domains
foreach ($allowed_domains as $index=>$allowed_domain) {
    # For a domain, allow 80, 443 ports by default
    echo "acl service_" . $index . "_domain dstdomain " . $allowed_domain . "\n";
    echo "http_access allow allowed_methods http_https_ports service_" . $index . "_domain\n";
    echo "\n";
}


?>