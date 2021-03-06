#!/usr/bin/env php
<?php

$version = null;
$newVersion = null;

if ($argc > 1) {
    if (preg_match('#^\d+\.\d+\.\d+$#', $argv[1])) {
        $version = $argv[1];
    } else {
        echo "Usage: {$argv[0]} [pod version] [new version]".PHP_EOL;
        exit(1);
    }

    if ($argc > 2) {
        $newVersion = $argv[2];
    }
}

foreach (['NIMSDK', 'NIMSDK_LITE'] as $name) {
    (new UpdatePodspec($name, $version, $newVersion))->update();
}

class UpdatePodspec
{
    protected $name;
    protected $version;
    protected $newVersion;
    protected $filename;

    public function __construct($name, $version, $newVersion = null)
    {
        $this->name = $name;
        $this->version = $version ?: $this->fetchPodLatestVersion($this->name);
        $this->newVersion = $newVersion ?: $this->patchedVersion($this->version);
        $this->filename = $this->name.'.podspec.json';
    }

    public function update()
    {
        echo "Updating {$this->name} {$this->version} -> {$this->newVersion}".PHP_EOL;

        $spec = $this->fetchPodspec();
        $spec = json_decode($spec, true);
        $spec['version'] = $this->newVersion;
        $spec = $this->replacePodSource($spec);
        // $spec = $this->addXcodeConfig($spec);

        $json = $this->encodePodspecToJSON($spec);
        file_put_contents(__DIR__.'/'.$this->filename, $json.PHP_EOL);
    }

    protected function fetchPodLatestVersion($name)
    {
        echo "Fetching the latest version for pod $name...";

        $versionsURL = 'https://cdn.cocoapods.org/all_pods_versions_'
            .$this->getPodSpecShard($name, '_').'.txt';
        $versions = $this->downloadContent($versionsURL);
        if (! preg_match('#^'.$name.'(/.+)?/([\d.]+)$#m', $versions, $matches)) {
            echo 'error'.PHP_EOL;
            exit(2);
        }
        $version = array_pop($matches);

        echo $version.PHP_EOL;

        return $version;
    }

    protected function getPodSpecShard($name, $seprator = '/')
    {
        return implode($seprator, str_split(substr(md5($name), 0, 3)));
    }

    protected function patchedVersion($version)
    {
        $parts = explode('.', $version);
        $lastNumber = array_pop($parts);
        if ($lastNumber == '0') {
            $parts[] = '001';
        } else {
            $parts[] = $lastNumber.'00';
        }

        return implode('.', $parts);
    }

    protected function fetchPodspec()
    {
        $url = 'https://raw.githubusercontent.com/CocoaPods/Specs/master/Specs/'
            .$this->getPodSpecShard($this->name, '/')
            .'/'.implode('/', [$this->name, $this->version, $this->filename]);

        if ($data = $this->downloadContent($url)) {
            return $data;
        }

        echo "Failed to fetch podspec from $url".PHP_EOL;
        exit(3);
    }

    protected function downloadContent($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $data = curl_exec($ch);

        if (curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            $data = false;
        }

        curl_close($ch);

        return $data;
    }

    /**
     * Replace the `http` protocol of `source` to `https`.
     *
     * - WARN  | http: The URL (`http://yx-web.nos.netease.com/package/1603355217/NIM_iOS_SDK_IM_v8.0.1.zip`) doesn't use the encrypted HTTPS protocol. It is crucial for Pods to be transferred over a secure protocol to protect your users from man-in-the-middle attacks. This will be an error in future releases. Please update the URL to use https.
     *
     * [!] 'NIMSDK_LITE' uses the unencrypted 'http' protocol to transfer the Pod. Please be sure you're in a safe network with only trusted hosts. Otherwise, please reach out to the library author to notify them of this security issue.
     */
    protected function replacePodSource($spec)
    {
        $url = $spec['source']['http'];
        if (strpos($url, 'https') === 0) {
            return $spec;
        }

        $components = parse_url($url);
        $url = 'https://yx-web-nosdn.netease.im'.$components['path']
            .'?download='.basename($components['path']);
        $spec['source']['http'] = $url;

        return $spec;
    }

    /**
     * Add `EXCLUDED_ARCHS = arm64` build setting for iOS Simulator.
     *
     * ld: building for iOS Simulator, but linking in dylib built for iOS, file 'NIMSDK.framework/NIMSDK' for architecture arm64
     *
     * References: https://stackoverflow.com/a/63955114/521946
     */
    protected function addXcodeConfig($spec)
    {
        $spec['user_target_xcconfig']
            = $spec['pod_target_xcconfig']
            = ['EXCLUDED_ARCHS[sdk=iphonesimulator*]' => 'i386 arm64'];

        return $spec;
    }

    protected function encodePodspecToJSON($spec)
    {
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Reduce the indentation size from 4 spaces to 2, to follow the style
        // of the `.podspec.json` file type.
        return preg_replace_callback('#^ +#m', function ($matches) {
            return str_repeat(' ', strlen($matches[0]) / 2);
        }, $json);
    }
}
