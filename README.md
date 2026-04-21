# Ai Generated Full Stack Forum
**Current Version** 77 (0.7.7)  

Full stack Forum built using Claude (Sonnet 4.6)  
I will post the code when we get to a release candidate...  

The very first version was 100% Ai generated, the next few versions were about 95% Ai with some user updates. The most current versions are about 50/50 and later versions will probably be less than 10% Ai and 90% human ;). I still upload all update to allow Claude to check them...    

![Example Image](https://github.com/phpbbireland/AiGF/blob/main/images/portalNew.jpg)

**First Prompt To Get Started:**  
+ Build a complete forum package using php and mysql with secure registration and login for members...  
+ Create some basic categories and sample posts...  
+ Add administration to allow adding, editing categories and sub-categories/forums...  
+ Users can comment on posts and create new posts...  

**Initial Steps:** (after first build)  
+ Separated HTML AND PHP in files...
+ Create basic template engine..
+ Created Assets folders/files...
+ Move CSS to dedicated css files...
+ Move functions from config.php to includes/functions.php...

**Other steps:**
+ Added Admin sort to Categories and Forums...
+ Added smilies to posts and Icons for Categories & Forums (ACP)...
+ Added upload Images (multiple, drag n drop) and LightBox to view full size...
+ Corrected post contents, lines being stripped of CR's...
+ Added Attachments table to database to store image data (post only show [attachment:x], simplified posts...
+ Added config option (currently: 10, more to follow): This allows add/edit all config options in ACP...
+ Reduced spacing/margins in admin pages...

  
**To Do (ACP)**  
+ Add more Admin Options +1
+ Email Settings
+ File upload & Settings
+ PM/DM
+ Upload Styles
+ Allow poll creation


**To Do (UCP)**
+ Add more user Options (UCP)
+ Email Settings
+ DOB settings
+ Timezone
+ Send/Receive PM/DM
+ Style Select
+ Poll Creation 

**To Do (maybe)**
+ Translate post
+ Form Mod
+ Portal
  
For example images see: [https://github.com/phpbbireland/AiGF/tree/main/images]

Example Post V0.5.0 ![Example Post](https://github.com/phpbbireland/AiGF/blob/main/images/post3.jpg)
Admin Dashboard V0.5.3 ![Admin Daskboard (ACP)](https://github.com/phpbbireland/AiGF/blob/main/images/admin1a.jpg)

**19th April 2026**
The basic index page looks good but is lacking a few frills.  
As I develop mods for phpBB2 & phpBB3 including the first ever portal for both, I have decided to add them to this forum build.  
The first one is the Menu Block, it provided quick links to ACP, UCP, Members etc., others will follow as time allows...  
Click on image to view full size, it will take you to the image foolder where there are more images: ![Index with Blocks, i.e. a portal](https://github.com/phpbbireland/AiGF/blob/main/images/index_with_portal_blocks.jpg)

Five or six themes added, user can select in profile panel...  

