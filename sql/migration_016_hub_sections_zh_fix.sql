-- Fix garbled UTF-8 in hub section Chinese copy (news/game/handhelds cards on hub home).
UPDATE hh_hub_sections SET
  title_zh = '掌机百科',
  desc_zh = '掌机规格、发布时间与英文介绍，独立子站持续更新。'
WHERE site_code = 'handhelds';

UPDATE hh_hub_sections SET
  title_zh = '怀旧游戏',
  desc_zh = '经典平台与游戏目录筹备中，以合规资料与外链为主。'
WHERE site_code = 'game';

UPDATE hh_hub_sections SET
  title_zh = '资讯精选',
  desc_zh = '硬件、独立复古与文化向内容聚合，后台抓取接入中。'
WHERE site_code = 'news';
