# AiGen4M (Ai Generated Full Stack Forum)

I built a forum using Claude and the prompts below.  
After initial prompt, it worked out of the box but required one minor non-critical fix.  

I have built a few simple projects with Ai but this was perhaps the smoothest one yet, requiring no input from me to get a working example forum.  

![Example Image](https://github.com/phpbbireland/AiGF/blob/main/images/forumbyClaude1.jpg)

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

Example Post ![Example Post](https://github.com/phpbbireland/AiGF/blob/main/images/post3.jpg)
Admin Dashboard ![Admin Daskboard (ACP)](https://github.com/phpbbireland/AiGF/blob/main/images/admin1a.jpg)

