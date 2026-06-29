# WordPress.org Release Checklist

The intended WordPress.org slug and text domain are `paykrypt-for-woocommerce`.

## Before submission

1. Create a WordPress.org account. If its username is not `paykrypt`, replace the `Contributors` value in `readme.txt` with the actual username.
2. Confirm the PayKrypt Terms of Use and Privacy Policy URLs in `readme.txt` remain current.
3. Run `composer install`, `composer test`, `composer phpcs`, and `composer audit --locked`.
4. Test activation on WordPress 7.0 with WooCommerce 10.9.
5. With a valid merchant API key, test a real low-value payment through classic checkout and Checkout Blocks. Confirm intent creation, redirect, polling, payment completion, expiry, and manual synchronization.
6. Confirm the versions in `readme.txt`, the plugin header, and `PAYKRYPT_WC_VERSION` match.

## Submission ZIP

The ZIP must contain one root directory named `paykrypt-for-woocommerce` with only:

```text
paykrypt-for-woocommerce/
  assets/
  includes/
  paykrypt-woocommerce.php
  readme.txt
```

Upload the ZIP at https://wordpress.org/plugins/developers/add/ and respond to Plugin Review Team email from the same address associated with the submitting account.

## First release after approval

WordPress.org will provide the authoritative approved slug and SVN URL. Replace `<approved-slug>` below if it differs from `paykrypt-for-woocommerce`.

```powershell
svn checkout https://plugins.svn.wordpress.org/<approved-slug> ..\<approved-slug>-svn
Copy-Item .\assets, .\includes -Destination ..\<approved-slug>-svn\trunk -Recurse
Copy-Item .\paykrypt-woocommerce.php, .\readme.txt -Destination ..\<approved-slug>-svn\trunk
Set-Location ..\<approved-slug>-svn
svn add --force trunk\*
svn copy trunk tags\0.1.0
svn add --force tags\0.1.0
svn status
svn commit -m "Release 0.1.0"
```

Use the case-sensitive WordPress.org username and its SVN-specific password when prompted. The SVN root-level `assets/` directory is reserved for directory icons, banners, and screenshots; runtime JavaScript remains in `trunk/assets/js/`.
