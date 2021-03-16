# Changelog of flusio

## 2021-03-xx - v0.18

### Breaking changes

If `APP_DEMO` is true, the reset of the application is now automatically done
via a scheduled job. The `make reset-demo` target is removed, you should remove
your cron task if you had one.

If you’ve set the subscription system, the sync cron task must be removed as
well (a scheduled job is running every 4 hours).

A new environment variable must be set in your `.env` file: `APP_SUPPORT_EMAIL`.
It’s used to create a default user. Make sure to set it to a user that doesn’t
exist since feeds are attached to it.

## 2021-02-23 - v0.17

### Breaking changes

Validation emails are now sent asynchronously by a jobs worker. First of all,
you must make sure to have installed the `pcntl` PHP extension. Then, please
have a look to the production documentation to learn how to setup the worker.

If you want to setup the Pocket importation system, you’ll have to create a
Pocket "consumer key". More information in the production documentation.

### News

- Add Pocket importations
- Allow users to change their avatar
- Allow to directly mark bookmarks as read

### Improvements

- Fetch actual content of Twitter pages
- Reword links "Show publicly" option in "Hide in public collections"
- Add pagination on collection page
- Add `aria-current="page"` on concerned anchors
- Remove icons of collections titles

### Bug fixes

- Fix the infinite redirection when discovering page was empty
- Fix username length issues
- Create validation token on "resend email" action if the token is missing

### Misc

- Setup an async jobs system
- Provide a DaoConnector trait and refactor code
- Extract the routes into a dedicated class
- Set docker-compose project name to `flusio`
- Update the README
- Reorganise technical documentation

## 2021-01-27 - v0.16

A new batch of fixes and improvements to deploy in production today!

### News

- Allow to delete messages
- Provide pagination on discovering page

### Improvements

- Display origin of news links
- Change wording for accepting terms of service
- Clarify important emails on registration
- Improve integration on various platforms
- Improve account nav menu
- Increase modal margin bottom on mobile
- Change default title font-family to sans-serif
- Add spacing under `.news__postpone`
- Remove some autofocus
- Lighten the layout border color
- Homogeneize card-action border width with footer

### Bug fixes

- Fix link to continue on step 4 of onboarding
- Fix modal undefined content
- Fix checkbox shrinking on mobile
- Fix section image on mobile
- Fix header background for Firefox on mobile

## 2021-01-22 - v0.15

This release brings mainly a lot of UI/UX improvements.

### Improvements

- Improve overall layout structure
    - Reorganize create/edit/delete buttons
    - Remove cancel actions from forms
    - Change body background
    - Add a border around content
- Improve links UX
    - Change link main action from "see" to "read"
    - Move actions from link show page to collection cards
    - Remove quick unbookmark button in cards
    - Simplify link show page
    - Remove sharing page
- Add links to web extension stores
- Remove shadow card from discover and public lists
- Hide "remove from news definitively" option
- Change card footer from turquoise to purple
- Change the green color in collections illustration to turquoise
- Create subscription accounts on Cron sync

### Misc

- Add service param in subscription login request
- Fix a SpiderBits test
- Fix the GitHub funding link
- Bump ParcelJS version
- Bump JS ini version to 1.3.8

## 2020-12-11 - v0.14

A small release for the “grand opening”!

### Improvements

- Provide better integration for browser extension

### Misc

- Bump ParcelJS version
- Increase default HTTP timeout
- Add log on subscriptions sync

## 2020-10-30 - v0.13

### News

- Allow to mark a single link as read

### Improvements

- Pick public news links in private collections
- Display a default card image for links with no image
- Move news preferences in a modal
- Improve readability of fieldsets
- Increase the quantity of blue in grey color
- Change cards titles to display block
- Add autofocus on security confirm password
- Add a pop-out icon on the subscription anchor
- Reword collections index section intro
- Improve details of the link fetching page
- Put "Show publicly" always at the end of forms
- Reword default option to select collections
- Change the color of selected collections
- Add a confirmation on "mark all as read"
- Add a bit of colors to cards footers
- Animate slowly the "body after" bar

### Bug fixes

- Fix popup menu position on mobile

## 2020-10-28 - v0.12

### Migration notes

Make sure that the GD PHP extension is installed with support of PNG, JPEG and
WEBP images.

You might need to reset some ids due to the bug fixed by [`df29d41`](https://github.com/flusio/flusio/commit/df29d413260cffa2d9f433139891c574ddcb504f).

(optional) flusio now supports Open Graph and Twitter Cards images. For oldest
links, you can refresh their image by running the following command:

```console
flusio$ # where NUMBER should be replaced by a positive integer value (default is 10)
flusio$ php cli --request /links/refresh -pnumber=NUMBER
```

### New

- Display Open Graph images on links
- Add batch actions at the bottom of news

### Improvements

- Improve the UX of news
- Improve unbookmark UX
- Save and redirect at step 4 of onboarding
- Improve visibility of `.popup__item` on hover
- Add light gradient backgrounds
- Change cursor on button hover to pointer
- Reduce line-height of cards titles
- Reorganize commands in CLI usage action

### Bug fixes

- Fix id on Link::initFromNews

### Misc

- Don't hide card--shadow on mobile per default
- Add French sync to PR template
- Add PHP gd extension requirement

## 2020-10-21 - v0.11

### Breaking changes

The ids of collections and links are changing to a numeric form, which means
previous URLs will break. This is not a change that I would do if the service
was open or installed by other people, at least not in a >= 1.0 release. Since
I'm almost the only person using it today and that I shared very few URLs, it’s
OK for me to do it. It's also the last occasion to make this change (or it
would require more work).

### Migration notes

(optional, mostly for myself) You can configure the subscription feature with
the `APP_SUBSCRIPTIONS_*` environment variables. Read production documentation
for more details.

### New

- Provide a subscription feature (monetization)
- Allow users to update login credentials
- Add a “terms of service” mecanism
- Block not validated users after 1 day

### Improvements

- Make explicit that JavaScript is required
- Change links and collections ids format (decimal instead of hexadecimal)
- Reorganize the "avatar" menu
- Reduce width of registration/login sections
- Improve extraction of websites title (again!)
- Hide back anchors on public page if no back URL
- Center submit button on profile page
- Homogeneize titles case
- Add a light linear gradient on popup menus
- Fix height of inputs

### Bug fixes

- Set cookies `SameSite` to `Lax`
- Generate user CSRF token directly instead of calling `Minz\CSRF`

### Misc

- Update Minz version
- Fix the locale in User factory
- Improve SpiderBits\Http get method
- Rename `format_date` to `format_message_date` and create a more generic
  `format_date()` function

## 2020-10-01 - v0.10

### Migration notes

(optional) You can change your instance brand name by setting `APP_BRAND` in
your `.env` file.

### New

- Allow to configure news
- Provide onboarding
- Allow to change the brand name

### Improvements

- Consider the OpenGraph and Twitter titles
- Redirect intelligently on link deletion
- Improve the news tips section
- Reword options to remove news
- Increase the topic label max size

### Bug fixes

- Don't select link in owned collection for news
- Hide "add to collections" if user has no collections
- Fix select width with long options on mobile
- Fix padding for header locale form

### Misc

- Add `[devmode]` in page title in development
- Fix break line in cards details
- Refactor listing with `human_implode` in News
- Fix NewsPicker duration test
- Fix a test to be sure to generate unique URLs

## 2020-09-24 - v0.9

### Migration notes

(optional) You can now create topics. Topics are attached to collections in
order to categorize them. Topics are created by the administrator with the CLI:

```console
flusio# php ./cli --request /topics/create -plabel=LABEL
```

### New

- Provide topics for collections
- Allow users to set their points of interest
- Get news suggestions from points of interest
- Provide public collections discovery
- Allow to delete a link

### Improvements

- Change default avatar
- Display if collection/link is public
- Improve the link and collection settings menus
- Add a card shadow to complete blocks of 3
- Add a light color on card:focus-within
- Add placeholder on public links without comments
- Display owner of followed collections
- Improve tips when there are no news
- Change links to close modals to buttons
- Improve `section__nav` margin on mobile
- Fix wording for private collection back button
- Go to previous page from public collection

### Bug fixes

- Refactor and fix "back" anchor on link pages
- Fix `<title>` for collections pages
- Fix cards design
- Hide titles overflow
- Return 404 if deleting non existing collection

### Misc

- Add support for rollbacks
- Update Parcel to beta-1
- Add support for serial ids in SaveHelper
- Add a test on cli usage command

## 2020-09-04 - v0.8

### Security

- Forbid access to not owned collections

### New

- Allow to create public collections
- Allow to follow collections
- Provide tips if there are no news to suggest
- Allow to permanently hide a news

### Improvements

- Allow to set link public during creation/edit
- Improve the process to add news to collections
- Move a bunch of actions in modals
- Improve the collections selector
- Improve the look of checkboxes
- Move public checkboxes at the end of forms
- Add a link to skip to the main content
- Add an anchor to go back from links add page
- Redirect directly to link page after fetch
- Add autofocus on a bunch of inputs
- Improve the look of navbar on mobile
- Change the news icon
- Put primary buttons on the right
- Add back anchor on public pages
- Add a light background to cards footers
- Fix links "collections" button padding
- Add few illustrations
- Improve and homogeneize wording

### Bug fixes

- Fix sanitization of HTML `<title>`
- Fix cards overflow
- Fix scrolling to top on Firefox
- Save backForLink on turbolinks:visit
- Fix margins of `.card__details`
- Hide marker of `.popup__opener` on Chrome

### Misc

- Extract a CSS card component
- Provide a modal mechanism
- Migrate the news system to a dedicated model
- Update French locales
- Change `include_once` by `include` for JS configuration
- Bump bl from 4.0.2 to 4.0.3

## 2020-08-21 - v0.7

### New

- Provide a basic system to read the "News"
- Provide a back anchor on link page

### Improvements

- Show link title on edit and collections
- Make a bunch of small design adjustments

### Misc

- Return l10n key if value doesn't exist (JS)

## 2020-08-06 - v0.6

### New

- Allow to comment links
- Allow to share public links

### Improvements

- Improve the (un)bookmark button
- Add the logo to the "not connected" header
- Improve look of buttons
- Set session cookie on registration
- Add anchors on cards titles
- Add a title on "manage" link collections

### Misc

- Update French locales
- Update the PR template
- Complete doc about release and update
- Split Links controller file
- Bump elliptic from 6.5.2 to 6.5.3
- Bump lodash from 4.17.15 to 4.17.19

## 2020-07-17 - v0.5

### Improvements

- Always show anchor to add links to collection
- Rename new collection description label
- Reduce time before fetching link
- Hide "www." from the links hosts
- Change "about" link to flus.fr

### Bug fixes

- Fix header UI details
- Fix bookmarks name localization
- Fix title scrapping for Youtube

### Misc

- Parse title only for HTML pages
- Update French locales

## 2020-07-16 - v0.4

### New

- Allow creation/edition/deletion of collections
- Provide a dedicated page to add links
- Manage collections from the link page
- Add support for mobiles

### Improvements

- Rework header bar
- Change style of hr tag
- Set Turbolinks progress bar style
- Move "edit/settings" anchor on link show page

### Bug fixes

- Set correct version in SpiderBits user agent

### Misc

- Update French locales
- Update icons
- Force CSRF token for connected users
- Homogeneize routes and controller actions names
- Provide a FakerHelper class for tests

## 2020-07-01 - v0.3

### Migration notes

If your instance is a demo, you should change the cron task which reset the
data by `make reset-demo NO_DOCKER=true`. This will reset the database and
create a demo user.

(optional) You can close registrations on your instance by setting the
`APP_OPEN_REGISTRATIONS` environment variable to `false`.

### New

- Allow to close registrations
- Allow to create users via the CLI

### Improvements

- Improve reading time calculation by removing script tags from dom content
- Decrease margins to put more content on small screens
- Add few illustrations
- Create a demo account during demo reset
- Select default locale based on http Accept-Language header
- Improve the look of success message when an account is deleted

### Bug fixes

- Fix title encoding on some websites
- Fix sessions lifetime by initializing the session from a custom cookie

### Misc

- Update French l10n

## 2020-06-26 - v0.2

### Migration notes

(optional) The responses from links fetching are now cached during one day. The
`APP_CACHE_PATH` env variable can be set in order to change the location of
cached responses (default is the flusio `cache/` folder).

### Improvements

- Puny-decode links host
- Don't lowercase links title
- Show a spinner animation during link fetching

### Misc

- Fix "reseted" typo
- Cache response during links fetching

## 2020-06-25 - v0.1

First version
