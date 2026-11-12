=== Where to Buy ===
Contributors: arbe
Tags: affiliate, where to buy, amazon, product, recommendations
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.1
License: GPLv2 or later

Show where to buy the things you recommend: a photo, a short description and a button for each store that sells it. Only stores with a valid link show, and the affiliate disclosure is added for you.

== Description ==

Website and guide: https://bignall.github.io/where-to-buy/

* A Where to Buy menu with its own items at /where-to-buy/your-item/, a list page at /where-to-buy/ and categories at /where-to-buy/category/name/. The slug can be changed in settings. Each item has a title, your photo (featured image), a short description (excerpt) and optional longer notes.
* Stores are set up once in Where to Buy → Store Settings: name, button image, the domains its links must go to, an optional affiliate tag (e.g. Amazon's tag=you-20, added to links that don't have it) and an optional extra disclosure (Amazon's "As an Amazon Associate I earn from qualifying purchases." comes filled in). Drag to set the button order; switch a store off to hide it everywhere.
* On each item, paste a link per store. A store's button only shows when its link is a full https address on one of that store's domains. The editor says which links show and why the others don't.
* Buttons use the store's image. Stores without one use the default image with the store name, or a text button when there's no default image either.
* The disclosure note ("Note: Commissions may be earned from the links above.", editable) shows right under the buttons, plus each shown store's extra disclosure. Links carry rel="sponsored nofollow noopener".
* Works with Affiliate Link Cloaker: any store can use one of its cloaked /go/ links instead of a pasted URL. Clicks are counted by the cloaker with the item's slug as the sub-ID, and links the cloaker marks dead (paused, banned, ended) are hidden.
* Built on WP Foundation (activation, upgrades, uninstall, multisite, deactivation survey).

== Placing items ==

On an item's own page the buy box is added after the content (turn this off in settings if your template places it).

Block: "Where to Buy" (choose an item; show the full card or just the buttons).

Shortcodes:

    [where_to_buy id="12"]                                       card: photo, title, description, buttons
    [where_to_buy slug="everyday-harness" show="buttons"]        just the buy box
    [where_to_buy_buttons]                                       buy box for the current item (for templates)
    [where_to_buy_list category="service-dog-gear" count="6"]   grid of cards; orderby, order, buttons="0"

REST: items are at /wp-json/wp/v2/wtb_item with a read-only `wtb_buttons` field (store, name, url, image).

== Styling ==

Colours follow Automatic.css tokens (--primary, --text-dark, --text-light) when present. Override --wtb-ink, --wtb-accent, --wtb-accent-ink, --wtb-surface, --wtb-line, --wtb-radius and --wtb-button-height on .wtb-box / .wtb-item to restyle. Classes: .wtb-box, .wtb-box__heading, .wtb-box__stores, .wtb-store (--image, --default, --text, --{store-id}), .wtb-box__note, .wtb-item, .wtb-item__photo, .wtb-item__title, .wtb-item__desc, .wtb-items.

== Hooks ==

* `wtb_item_slug` (default: the slug from settings, "where-to-buy"): URL base.
* `wtb_buttons( $buttons, $item_id )`: filter the buttons that show.
* `wtb_box_html( $html, $item_id )`, `wtb_card_html( $html, $item_id )`: filter the output.

== Notes ==

* Use your own photos. Amazon's Associates agreement doesn't allow downloading and re-hosting its product images.
* Store logos are trademarks; check each store's brand rules before using theirs.

== Changelog ==

= 0.3.1 =
* Items use the classic editor, with the short description and store links right under the title. Use the `wtb_item_block_editor` filter to switch back.

= 0.3.0 =
* Settings split into General Settings (web address, buy box) and Store Settings (stores and the default button image).

= 0.2.0 =
* Renamed Picks to Where to Buy. Default address is now /where-to-buy/, and it can be changed in settings. Shortcodes are now [where_to_buy], [where_to_buy_buttons] and [where_to_buy_list]. Existing items and blocks are moved over on upgrade.
* Fixed a "translation loading was triggered too early" notice on activation.

= 0.1.0 =
* First version.
