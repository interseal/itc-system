# Обработать PDF-каталог, фото или видео
Когда: достать фото из каталога, картинки из PDF, сжать видео, кадры из видео, фото квадратом на белом фоне, текст из PDF
---
1) Файл приложить в чат Фила/«Космоса» (PDF до 12 МБ, видео до 20 МБ) или дать ссылку (до 40 МБ).
2) Инструмент files: op = pdf_images (фото товаров; from/to — страницы, min — мелкие пропустить), pdf_pages (страницы картинками, если скан), pdf_text, img (size, format, square, white, trim), video_frames (count), video_small (height), video_cut (from, len), info.
3) Обрабатывает сервер «Космоса» (lib/media.php, шлюз a=media); результат — ссылки itcpartner.store/uploads/fil_out/… и all.zip.
4) Нет вшитых картинок — значит каталог из сканов: pdf_pages.
