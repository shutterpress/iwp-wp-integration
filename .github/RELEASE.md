# Release FluentCart InstaWP Provisioning

Update Version and FCIP_VERSION in fluentcart-instawp-provisioning.php, the Stable tag in readme.txt, and CHANGELOG.md. Run the PHP and JavaScript checks.

Create and push a matching vX.Y.Z tag. The release workflow packages fluentcart-instawp-provisioning-X.Y.Z.zip with fluentcart-instawp-provisioning/ as its root directory, then publishes the release. No release is published merely by opening or merging a pull request.

For a local ZIP, run bash build-zip.sh. The updater reads releases from shutterpress/iwp-wp-integration and accepts only the matching FluentCart plugin ZIP asset.
