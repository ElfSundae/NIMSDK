# NIMSDK

[![Build status](https://github.com/ElfSundae/NIMSDK/workflows/Build/badge.svg)](https://github.com/ElfSundae/NIMSDK/actions?query=workflow%3ABuild)

Unofficial NIMSDK podspec to fix integration issues and build errors.

- Replaced the `http` protocol of `source` to `https`, to fix a installation warning: _'NIMSDK_LITE' uses the unencrypted 'http' protocol to transfer the Pod._
- Added `EXCLUDED_ARCHS[sdk=iphonesimulator*] = arm64` build setting for iOS Simulator, to fix a build error: _ld: building for iOS Simulator, but linking in dylib built for iOS, file 'NIMSDK.framework/NIMSDK' for architecture arm64_
