-- =============================================================================
-- Migration: backfill ISINs and rename share classes per Desmond's FundHub
-- spec (FundHub_Sample_16.01.2026.xlsx). Idempotent — safe to re-run.
-- =============================================================================

SET @ee  = (SELECT id FROM funds WHERE slug = 'mori-eastern-european-fund');
SET @ott = (SELECT id FROM funds WHERE slug = 'mori-ottoman-fund');

-- Eastern European Fund share classes
UPDATE share_classes SET isin = 'IE0002787442', name = 'EE Class A EUR'  WHERE fund_id = @ee  AND name IN ('Class A EUR','EE Class A EUR');
UPDATE share_classes SET isin = 'IE00B53RTW70', name = 'EE Class B EUR'  WHERE fund_id = @ee  AND name IN ('Class B EUR','EE Class B EUR');
UPDATE share_classes SET isin = 'IE00B74GCZ17', name = 'EE Class AA GBP' WHERE fund_id = @ee  AND name IN ('Class AA GBP','EE Class AA GBP');
UPDATE share_classes SET isin = 'IE00B762ZY72', name = 'EE Class C GBP'  WHERE fund_id = @ee  AND name IN ('Class C GBP','EE Class C GBP');
UPDATE share_classes SET isin = 'IE00BD03V952', name = 'EE Class M EUR'  WHERE fund_id = @ee  AND name IN ('Class M EUR','EE Class M EUR');

-- Ottoman Fund share classes
UPDATE share_classes SET isin = 'IE00B0T0FN89', name = 'Otto Class A EUR'  WHERE fund_id = @ott AND name IN ('Class A EUR','Otto Class A EUR');
UPDATE share_classes SET isin = 'IE00B4XYZP64', name = 'Otto Class C USD'  WHERE fund_id = @ott AND name IN ('Class C USD','Otto Class C USD');
UPDATE share_classes SET isin = 'IE00B8G12179', name = 'Otto Class C EUR'  WHERE fund_id = @ott AND name IN ('Class C EUR','Otto Class C EUR');
UPDATE share_classes SET isin = 'IE00B87PYK12', name = 'Otto Class C GBP'  WHERE fund_id = @ott AND name IN ('Class C GBP','Otto Class C GBP');
UPDATE share_classes SET isin = 'IE00B87G5S97', name = 'Otto Class AA GBP' WHERE fund_id = @ott AND name IN ('Class AA GBP','Otto Class AA GBP');
UPDATE share_classes SET isin = 'IE00BJLC3Y24', name = 'Otto Class M USD'  WHERE fund_id = @ott AND name IN ('Class M USD','Otto Class M USD');
