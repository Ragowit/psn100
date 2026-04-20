# PSN 100% <[https://psn100.net](https://psn100.net)>

## What is PSN 100%?
PSN 100% is a trophy tracking website, focusing on merging game stacks and removing unobtainable trophies to create one list of only unique obtainable trophies so all users have the chance to compete for the same level, without the need to replay the same game multiple times or missed opportunities on trophies that are no longer available for one reason or another. Furthermore PSN 100% only calculates stats from the top 10k players in order to try and be more accurate for those who consider themselves as a trophy hunter. PSN 100% is made by trophy hunters, for trophy hunters.

## What isn't PSN 100%?
PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.

## Technology stack
- **PHP:** 8.5
- **MySQL:** 8.4


## Application configuration

The canonical service config file is `wwwroot/config/app.php`.

### PSN integration architecture

PSN100 now uses a single internal PSN client implementation for all PSN operations:

- admin player lookup (`PsnPlayerLookupService`)
- admin game lookup and trophy retrieval (`PsnGameLookupService`)
- game rescan workflow (`GameRescanService`)
- 30-minute worker cron sync (`ThirtyMinuteCronJob`)

`PlayStationClientFactory` always returns the internal `NativePlayStationApiClient` implementation and no longer performs shadow/legacy dual execution.

### Operational guidance

- Worker NPSSO values are still the authentication source-of-truth and should be rotated periodically.
- If PSN auth starts failing, verify worker NPSSO validity first, then validate outbound network access to Sony auth/profile/trophy endpoints.
- Prefer observing application logs around worker authentication and PSN request failures before retrying bulk scans.

## Merge Guideline Priorities
1. Available > Delisted
2. English language > Other language
3. Digital > Physical
4. Remaster/Remake > Original
5. PS5 > PS4 > PS3 > PSVITA
6. Collection/Bundle > Single entry

## Thanks
- PSNP+ <[https://psnp-plus.netlify.app/](https://psnp-plus.netlify.app/)> ([HusKy](https://forum.psnprofiles.com/profile/229685-husky/)) for allowing PSN100 to use the "Unobtainable Trophies Master List" data.

## Other sites
- PSN Profiles <[https://psnprofiles.com/](https://psnprofiles.com/)>
- PlaystationTrophies <[https://www.playstationtrophies.org/](https://www.playstationtrophies.org/)>
- PSN Trophy Leaders <[https://psntrophyleaders.com/](https://psntrophyleaders.com/)>
- TrueTropies <[https://www.truetrophies.com/](https://www.truetrophies.com/)>
- Exophase <[https://www.exophase.com/](https://www.exophase.com/)>
