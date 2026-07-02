-- Blogger publish tracking on handheld row (independent from hh_blogger_posts API sync)
USE handheld_hub;

ALTER TABLE hh_handhelds
  ADD COLUMN blogger_mark ENUM('none','published') NOT NULL DEFAULT 'none' AFTER status,
  ADD KEY idx_blogger_mark (blogger_mark);

-- Backfill: both locales already published on Blogger
UPDATE hh_handhelds h
SET h.blogger_mark = 'published'
WHERE EXISTS (
  SELECT 1 FROM hh_blogger_posts pz
  WHERE pz.handheld_id = h.id AND pz.locale = 'zh' AND pz.sync_status = 'published'
)
AND EXISTS (
  SELECT 1 FROM hh_blogger_posts pe
  WHERE pe.handheld_id = h.id AND pe.locale = 'en' AND pe.sync_status = 'published'
);
