=== Where to Buy ===
Contributors: bignall
Tags: affiliate, where to buy, amazon, product, recommendations
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.9.1
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

Blocks: "Where to Buy" (choose an item; show the full card or just the buttons) and "Where to Buy: Store buttons" (the buttons for the current item, for templates and Query Loops; heading and disclosure notes can be switched off).

Templates: with a block theme on WordPress 6.7+, the plugin supplies basic templates for an item's page and the list page (also used for categories). Your theme's own templates, or ones you edit in the Site Editor, take over. Turn them off with the `wtb_block_templates` filter. The list page title, introduction and disclosure are set in General Settings → List page.

Shortcodes:

    [where_to_buy id="12"]                                       card: photo, title, description, buttons
    [where_to_buy slug="everyday-harness" show="buttons"]        just the buy box
    [where_to_buy_buttons]                                       buy box for the current item (for templates)
    [where_to_buy_buttons heading="" notes="0"]                  just the buttons, no heading or notes
    [where_to_buy_list category="service-dog-gear" count="6"]   grid of cards; orderby, order, buttons="0"
    [where_to_buy_intro]                                         list page introduction (not on category pages)
    [where_to_buy_disclosure]                                    list page disclosure, link and store disclosures
    [where_to_buy_back label="← All items"]                     link back to the list page
    [where_to_buy_categories all="Everything"]                   links to the list page and each category

REST: items are at /wp-json/wp/v2/wtb_item with a read-only `wtb_buttons` field (store, name, url, image).

== Styling ==

Colours follow Automatic.css tokens (--primary, --text-dark, --text-light) when present. Override --wtb-ink, --wtb-accent, --wtb-accent-ink, --wtb-surface, --wtb-line, --wtb-radius and --wtb-button-height on .wtb-box / .wtb-item to restyle. Classes: .wtb-box, .wtb-box__heading, .wtb-box__stores, .wtb-store (--image, --default, --text, --{store-id}), .wtb-box__note, .wtb-item, .wtb-item__photo, .wtb-item__title, .wtb-item__desc, .wtb-items.

== Hooks ==

* `wtb_item_slug` (default: the slug from settings, "where-to-buy"): URL base.
* `wtb_buttons( $buttons, $item_id )`: filter the buttons that show.
* `wtb_box_html( $html, $item_id )`, `wtb_card_html( $html, $item_id )`, `wtb_disclosure_html( $html )`: filter the output.
* `wtb_block_templates` (default true): register the basic block theme templates.

== Notes ==

* Use your own photos. Amazon's Associates agreement doesn't allow downloading and re-hosting its product images.
* Store logos for the default stores are a separate download: https://github.com/bignall/where-to-buy/releases/latest/download/where-to-buy-store-logos.zip (public domain files from Wikimedia Commons). They are trademarks; check each store's brand rules before using theirs.

== Changelog ==

= 0.9.1 =
* The store logos download now covers Etsy, Barnes & Noble, The Home Depot, Lowe's, Costco, Petco and PetSmart too (with white versions of Petco and Barnes & Noble), and Add logos from your computer matches them to those stores.

= 0.9.0 =
* Add store now offers a list of known stores with their domains filled in (Amazon, Walmart, Best Buy, Target, Chewy, eBay, Etsy, Bookshop.org, Barnes & Noble, The Home Depot, Lowe's, Costco, Petco, PetSmart, REI), or a blank store for your own. Stores you already have are crossed out.
* Button images can be uploaded straight from your computer: Upload on each store adds the file to the Media Library and uses it.
* Add logos from your computer: pick several logos at once (such as the store logos download) and each goes to the store it's named after.

= 0.8.0 =
* eBay is one of the stores new installs start with (domains ebay.com and ebay.us). Existing sites can add it in Store Settings.
* Store logos are now a separate download, linked from Store Settings: Amazon, Walmart, Best Buy, Target, Chewy and eBay, with icon and white versions where available.

= 0.7.0 =
* Move or back up (General Settings): export everything to one file, and import it on another site. The file holds your stores (affiliate tags, disclosures, button images), settings, categories, and every item with its links and images, with the images inside the file. Importing matches stores by name, skips items that are already there, keeps the new site's URL slug, and matches cloaked links by name or links straight to the store.
* Moved to (General Settings → Web address): after a move, the old site's item, list and category pages redirect to the same page on the new site (301).

= 0.6.1 =
* The plugin's author is now RB Creative Solutions.

= 0.6.0 =
* Works with To-do Hub: adds "Fix store links on <item>" when a published item's store links don't all show (high priority when no button shows), and "Review N items waiting for approval" while items are pending. Tasks are marked done when fixed. Checked on save, when store settings change, and daily. Turn off under General Settings → To-do list.

= 0.5.0 =
* Basic templates for block themes (WordPress 6.7+): an item's page and the list page, used until your theme or the Site Editor provides its own.
* New "Where to Buy: Store buttons" block for templates and Query Loops.
* General Settings → List page: title, introduction, disclosure and disclosure page. New shortcodes [where_to_buy_intro], [where_to_buy_disclosure], [where_to_buy_back] and [where_to_buy_categories]; [where_to_buy_buttons] takes notes="0".
* The buy box is no longer added twice when a template already shows it above the content.

= 0.4.2 =
* Category pages use the Where to Buy list page's template when the theme has no category template, instead of the theme's bare default. A taxonomy-wtb_item_category template in the theme still takes over.

= 0.4.1 =
* Fixed category pages such as /where-to-buy/category/books/ showing "not found". The item address rules were catching them first.

= 0.4.0 =
* Amazon product links are shortened when an item is saved: search tracking is dropped and the link becomes amazon.com/dp/ASIN/?tag=yourtag, keeping the link's own tag or your Amazon tag from Store Settings. Works for other Amazon sites and for amzn.to and a.co links. Turn it off with "Shorten Amazon links" in General Settings, or with the `wtb_normalize_store_url` filter.

= 0.3.1 =
* Items use the classic editor, with the short description and store links right under the title. Use the `wtb_item_block_editor` filter to switch back.

= 0.3.0 =
* Settings split into General Settings (web address, buy box) and Store Settings (stores and the default button image).

= 0.2.0 =
* Renamed Picks to Where to Buy. Default address is now /where-to-buy/, and it can be changed in settings. Shortcodes are now [where_to_buy], [where_to_buy_buttons] and [where_to_buy_list]. Existing items and blocks are moved over on upgrade.
* Fixed a "translation loading was triggered too early" notice on activation.

= 0.1.0 =
* First version.
