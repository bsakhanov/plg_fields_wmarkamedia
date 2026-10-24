# Создание репозитория и выпуск версий

Имя репозитория в файлах — `bsakhanov/plg_fields_wmarkamedia`. Если назовёте иначе, замените его в четырёх местах (список: `grep -rn bsakhanov .`): `build.php` (`OWNER`, `REPO`), `src/wmarkamedia.xml` (`<updateservers>`), `README*.md`, `updates.xml`.

## 1. Создать репозиторий на GitHub

1. На GitHub: New repository → имя `plg_fields_wmarkamedia`, **Public**, **без** README, `.gitignore` и лицензии (они уже есть в проекте, иначе будет конфликт истории).
2. Settings → Actions → General → Workflow permissions → **Read and write permissions** (нужно, чтобы CI мог создать релиз).

## 2. Загрузить проект

**Способ А: из файла `plg_fields_wmarkamedia.bundle`** (в нём уже есть коммит и тег `v1.1.1`):

```
git clone plg_fields_wmarkamedia.bundle plg_fields_wmarkamedia
cd plg_fields_wmarkamedia
git remote set-url origin https://github.com/bsakhanov/plg_fields_wmarkamedia.git
git config user.name "Ваше имя"
git config user.email "ваш-адрес@users.noreply.github.com"
git commit --amend --reset-author --no-edit        # автор коммита в архиве задан условно
git tag -f -a v1.1.1 -m "Wmarka Media 1.1.1"       # тег переносим на исправленный коммит
git push -u origin main
git push origin v1.1.1                             # по тегу CI соберёт архив и создаст релиз
```

**Способ Б: из архива `plg_fields_wmarkamedia-repo.zip`**: распаковать, затем `git init -b main`, `git add .`, `git commit -m "Wmarka Media 1.1.1"`, `git remote add origin …`, `git push -u origin main`, `git tag -a v1.1.1 -m "Wmarka Media 1.1.1"`, `git push origin v1.1.1`.

## 3. Проверить релиз и сервер обновлений

1. Во вкладке Actions должен завершиться запуск CI; в Releases появится `v1.1.1` с файлом `plg_fields_wmarkamedia-1.1.1.zip`. (Файл CI на GitHub при подготовке не запускался: если запуск упал, пришлите его журнал или приложите к релизу архив вручную.)
2. Откройте адрес сервера обновлений и убедитесь, что он отдаёт XML: `https://raw.githubusercontent.com/bsakhanov/plg_fields_wmarkamedia/main/updates.xml`.
3. Откройте адрес из `<downloadurl>` в `updates.xml` — должен скачаться архив.
4. На тестовом сайте: «Система → Обновление → Расширения → Найти обновления».

## 4. Выпуск следующей версии

1. Поднять версию в `src/wmarkamedia.xml` (`<version>`) и в шапке `src/media/js/wmarkamedia.js`; дописать `CHANGELOG.md`.
2. `php tests/run.php && php build.php --check`.
3. Коммит и тег: `git commit -am "…" && git tag -a v1.2.0 -m "…" && git push origin main v1.2.0`. CI соберёт архив и создаст релиз.
4. **Только после того как релиз появился**, запишите версию в сервер обновлений: `php build.php` (добавит запись в `updates.xml`), затем `git commit -am "updates.xml: 1.2.0" && git push`. Если опубликовать запись раньше релиза, Joomla предложит обновление, ссылка на которое ещё не открывается.

## 5. Контрольная сумма в updates.xml

По умолчанию `updates.xml` пишется **без** `<sha256>`: байты архива, собранного в GitHub Actions, могут отличаться от собранного у вас, и тогда Joomla отвергнет обновление. Если вы прикладываете к релизу именно собранный у себя архив (без CI), соберите так: `php build.php --checksum`, затем приложите `dist/plg_fields_wmarkamedia-<версия>.zip` к релизу без пересборки.

## 6. Что внутри

См. таблицу в `README.md`, раздел «Разработка и сборка».
