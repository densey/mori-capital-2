-- 2026-10-how-to-complain-page.sql
-- "How to Complain" page (footer link → /how-to-complain), wording supplied by
-- Mori Capital Management. Editable afterwards in Admin → Pages
-- (slug how-to-complain, EN and DE). Existing rows are never overwritten.

INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('how-to-complain', 'en', 'How to Complain', 'How to Complain — Mori Capital Management',
 'How to make a complaint about the services of Mori Capital Management Ltd. and how complaints are handled.',
 '<h2>Complaint Handling</h2>
<p>If you wish to make a complaint about the services rendered by Mori Capital Management Limited, please contact:</p>
<p><strong>Isidro Garcia de la Torre</strong> (Compliance Officer / MLRO)<br>Office 35 – Regent House<br>Bisazza Street<br>Sliema<br>SLM1640<br><a href="tel:+35620330110">+356 2033 0110</a></p>
<p>If you have a complaint about the person who advised you about or sold you a fund managed by the Company, please contact them for their complaints process.</p>
<h2>Complaint Process</h2>
<p>Mori’s complaint handling process applies to all complaints received by the company via any means (phone, email, social media, etc).</p>
<p>Once your complaint has been received and acknowledged, we will try to resolve it within 15 working days. We will contact you by telephone or email to let you know about the steps we have taken to resolve your complaint.</p>
<p>If we haven’t resolved your complaint within 15 working days or you do not agree with the resolution proposed by us, we will continue our investigation to find out what has gone wrong and what we can do to make it right. In that event, we will contact you again within 35 working days from receipt of the complaint to let you know about our final response, or explain why we have been unable to resolve your complaint.</p>
<p>If you are not happy with the resolution provided by Mori, or we have been unable to resolve it within 35 working days, you may be entitled to refer your complaint to the Office of the Arbiter for Financial Services (OAFS)</p>
<p>N/S in Regional Road<br>Msida MSD 1920<br>Malta<br>Freephone Malta: <a href="tel:80072366">8007 2366</a><br>Telephone: <a href="tel:+35621249245">+356 21249245</a></p>',
 'published'),
('how-to-complain', 'de', 'Beschwerde einreichen', 'Beschwerde einreichen — Mori Capital Management',
 'Wie Sie eine Beschwerde über die Dienstleistungen von Mori Capital Management Ltd. einreichen und wie Beschwerden bearbeitet werden.',
 '<h2>Beschwerdebearbeitung</h2>
<p>Wenn Sie eine Beschwerde über die von Mori Capital Management Limited erbrachten Dienstleistungen einreichen möchten, wenden Sie sich bitte an:</p>
<p><strong>Isidro Garcia de la Torre</strong> (Compliance Officer / MLRO)<br>Office 35 – Regent House<br>Bisazza Street<br>Sliema<br>SLM1640<br><a href="tel:+35620330110">+356 2033 0110</a></p>
<p>Wenn Sie eine Beschwerde über die Person haben, die Sie zu einem von der Gesellschaft verwalteten Fonds beraten oder Ihnen diesen verkauft hat, wenden Sie sich bitte an diese, um Informationen zu deren Beschwerdeverfahren zu erhalten.</p>
<h2>Beschwerdeverfahren</h2>
<p>Das Beschwerdeverfahren von Mori gilt für alle Beschwerden, die bei der Gesellschaft auf beliebigem Weg eingehen (Telefon, E-Mail, soziale Medien usw.).</p>
<p>Sobald Ihre Beschwerde eingegangen ist und wir den Eingang bestätigt haben, bemühen wir uns, sie innerhalb von 15 Arbeitstagen zu lösen. Wir informieren Sie telefonisch oder per E-Mail über die Schritte, die wir zur Lösung Ihrer Beschwerde unternommen haben.</p>
<p>Sollten wir Ihre Beschwerde nicht innerhalb von 15 Arbeitstagen gelöst haben oder sollten Sie mit der von uns vorgeschlagenen Lösung nicht einverstanden sein, setzen wir unsere Untersuchung fort, um herauszufinden, was schiefgelaufen ist und wie wir es wiedergutmachen können. In diesem Fall melden wir uns innerhalb von 35 Arbeitstagen nach Eingang der Beschwerde erneut bei Ihnen, um Ihnen unsere abschließende Antwort mitzuteilen oder zu erläutern, warum wir Ihre Beschwerde nicht lösen konnten.</p>
<p>Wenn Sie mit der von Mori angebotenen Lösung nicht zufrieden sind oder wir Ihre Beschwerde nicht innerhalb von 35 Arbeitstagen lösen konnten, sind Sie möglicherweise berechtigt, Ihre Beschwerde an das Office of the Arbiter for Financial Services (OAFS) zu richten:</p>
<p>N/S in Regional Road<br>Msida MSD 1920<br>Malta<br>Gebührenfrei (Malta): <a href="tel:80072366">8007 2366</a><br>Telefon: <a href="tel:+35621249245">+356 21249245</a></p>',
 'published')
ON DUPLICATE KEY UPDATE id = id;
