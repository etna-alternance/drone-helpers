#!/usr/bin/env php
<?php

require "./vendor/autoload.php";

$github_client = new GuzzleHttp\Client(
    [
        "base_url" => "https://api.github.com",
        "defaults" => [
            "headers" => [
                "Authorization" => "token " . getenv("GITHUB_TOKEN"),
            ]
        ]
    ]
);

$drone_client = new GuzzleHttp\Client(
    [
        "base_url" => "http://drone.etna-alternance.net",
        "defaults" => [
            "query" => [
                "access_token" => getenv("DRONE_TOKEN"),
            ]
        ]
    ]
);

$repos = $drone_client->get("/api/user/repos")->json();
$repos = array_filter(
    $repos,
    function ($repo) {
        return $repo["host"] === "github.com" && $repo["owner"] === "etna-alternance" && $repo["active"] === true;
    }
);

foreach ($repos as $repo) {
    try {
        echo "----> Checking {$repo["owner"]}/{$repo["name"]}\n";
        echo "    ----> Getting drone public key for {$repo["owner"]}/{$repo["name"]}\n";
        $public_key = $drone_client->get("/api/repos/github.com/{$repo["owner"]}/{$repo["name"]}")->json()["public_key"];

        echo "    ----> Checking github keys for {$repo["owner"]}/{$repo["name"]}\n";
        $ssh_keys = array_filter(
            $github_client->get("/repos/{$repo["owner"]}/{$repo["name"]}/keys")->json(),
            function ($repo) use ($public_key) {
                return trim($repo["key"]) == trim($public_key);
            }
        );

        if (empty($ssh_keys)) {
            echo "    ----> Adding github key to {$repo["owner"]}/{$repo["name"]}\n";
            $add_response = $github_client->post(
                "/repos/{$repo["owner"]}/{$repo["name"]}/keys",
                [
                    "json" => [
                        "title" => "drone@drone.etna-alternance.net",
                        "key"   => $public_key,
                    ],
                ]
            );
        }
    } catch (GuzzleHttp\Exception\ClientException $e) {
        echo $e->getMessage(), "\n";
        print_r($e->getResponse()->json());
        break;
    }
}
