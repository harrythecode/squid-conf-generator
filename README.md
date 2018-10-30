**Squid Config Generator**
--------------------------

This is an API which consumes a ​service description in JSON format and produces a ​squid proxy configuration to limit egress traffic from the environment.

*   **URL** [/gen-squid-conf.php](/gen-squid-conf.php)

*   **Method** `POST`

*   **Data Params**

    *   Header: Content-type: application/json
    *   Body Data:
~~~
        {
          "service_name": "test-service",
          "eu": {
              "production": {
                  "service_id": "eu_staging",
                  "container_memory": 1500,
                  "container_cpu": 256,
                  "egress": {
                      "allowed_ips": [
                          "192.168.1.1:80",
                          "192.168.1.1:443",
                          "192.168.1.1:22",
                          "192.168.1.254:23",
                          "8.8.8.8:53",
                          "8.8.4.5:53"
                      ],
                      "allowed_domains": [
                          "facebook.com",
                          "google.com",
                          ".amazonaws.com",
                          "npmjs.com"
                      ]
                  }
              }
          }
        }
~~~

*   **Success Response:**

  *   **Code:** 200  
      **Content:**
      ~~~
      ###
      # Squid Config Generator v1.0, August 2018 by Harry
      ###

      ### Default Policy
      # Allow HTTP methods
      acl allowed_methods method CONNECT GET POST HEAD

      # Define the default ports for http/https
      acl http_https_ports port 80 443

      #
      # Recommended secure configuration:
      #

      # Hide some information from a client via http/https protocol
      # HTTP_VIA 1.1 localhost (squid/3.5.20)
      #       -> 1.1 unknown (squid/3.5.20)
      visible_hostname unknown

      # HTTP_X_FORWARDED_FOR (Client IP Address)
      #                   -> unknown
      forwarded_for off

      # Remove the headers
      request_header_access Via deny all
      request_header_access X-FORWARDED-FOR deny all

      # Listen on 58080 port
      http_port 58080

      ### Custom Policy
      #
      # Created configuration with the service description:
      #
      acl service_0_ip dstdomain 192.168.1.1
      acl service_0_ip_port port 22 443 80
      http_access allow allowed_methods service_0_ip_port service_0_ip

      acl service_1_ip dstdomain 192.168.1.254
      acl service_1_ip_port port 23
      http_access allow allowed_methods service_1_ip_port service_1_ip

      acl service_2_ip dstdomain 8.8.4.5
      acl service_2_ip_port port 53
      http_access allow allowed_methods service_2_ip_port service_2_ip

      acl service_3_ip dstdomain 8.8.8.8
      acl service_3_ip_port port 53
      http_access allow allowed_methods service_3_ip_port service_3_ip

      acl service_0_domain dstdomain .amazonaws.com
      http_access allow allowed_methods http_https_ports service_0_domain

      acl service_1_domain dstdomain facebook.com
      http_access allow allowed_methods http_https_ports service_1_domain

      acl service_2_domain dstdomain google.com
      http_access allow allowed_methods http_https_ports service_2_domain

      acl service_3_domain dstdomain npmjs.com
      http_access allow allowed_methods http_https_ports service_3_domain
      ~~~


*   **Error Response:**
    *   **Code:** 200  
        **Content:** `Usage: Content-Type should be 'application/json'. HTTP Method should be 'POST'.`


*   **Sample Call:**

    `curl --request POST /gen-squid-conf.php -d @example-service-description.json --header "Content-Type: application/json"`


*   **URL Params**

    **Optional:**

    *   `debug=on`
        *   You can see more information like below:
          ~~~
          ##########
          Debug Mode
          ##########
          1. Region and Environment
          Region: Set as eu
          Environment: Set as production
          2. Elements of allowed ips (removed duplicates)
          Array
          (
              [0] => 192.168.1.1:22 443 80
              [1] => 192.168.1.254:23
              [2] => 8.8.4.5:53
              [3] => 8.8.8.8:53
          )
          3. Elements of allowed domains
          Array
          (
              [0] => .amazonaws.com
              [1] => facebook.com
              [2] => google.com
              [3] => npmjs.com
          )
          ~~~

    By default, the API looks into the json param `{ "eu": "production": {"egress": {"allowed_ips": [], "allowed_domains": []} } }`

    *   `region=[any]`

        *   For example, you changed "eu" to "asia" in your description file, then you can also call the API: `curl --request POST /gen-squid-conf.php?region=asia -d @example-service-description.json --header "Content-Type: application/json"`
    *   `environment=[any]`

        *   For example, you changed "production" to "staging" in your description file, then you can also call the API: `curl --request POST /gen-squid-conf.php?environment=staging -d @example-service-description.json --header "Content-Type: application/json"`

