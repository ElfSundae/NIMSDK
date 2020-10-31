# NIMSDK

Unofficial NIMSDK podspec to fix integration issues and build errors.

- Replaced the `http` protocol of `source` to `https` in the podspec, to fix a installation warning: _'NIMSDK_LITE' uses the unencrypted 'http' protocol to transfer the Pod._
- Added `EXCLUDED_ARCHS = arm64` build setting for iOS Simulator, to fix a build error: _ld: building for iOS Simulator, but linking in dylib built for iOS, file 'NIMSDK.framework/NIMSDK' for architecture arm64_
