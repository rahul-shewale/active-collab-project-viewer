# ActiveCollab Dashboard

A lightweight, self-hosted PHP dashboard for viewing ActiveCollab project tasks, comments, and attachments — with a built-in image proxy so authenticated images load correctly in the browser.

---

## Features

- Project stats — total tasks, open, completed, task lists, members
- Expandable task cards with descriptions and metadata
- Comments with timestamps and author names
- Image attachments rendered inline via PHP proxy
- File attachments (PDF, Word, Excel, ZIP, video, audio) with download links
- Live search across task titles, descriptions, and comments
- Overdue / upcoming due date highlighting
- Responsive layout for mobile and desktop

---

## Requirements

- PHP 7.4 or higher
- `curl` extension enabled
- ActiveCollab self-hosted instance with API access

---

## Setup

### 1. Download the file

Save `ActiveCollab.php` to a folder on your machine.

### 2. Configure your credentials

Open `ActiveCollab.php` and update the three config values near the top:

```php
$BASE_URL   = 'https://your-activecollab-domain.com';
$TOKEN      = 'YOUR_API_TOKEN_HERE';
$PROJECT_ID = 1234;
```

| Variable     | Description                                              |
|--------------|----------------------------------------------------------|
| `$BASE_URL`  | Your ActiveCollab instance URL (no trailing slash)       |
| `$TOKEN`     | Your API token from ActiveCollab → Profile → API Tokens  |
| `$PROJECT_ID`| The numeric ID of the project you want to display        |

### 3. Find your Project ID

Go to your project in ActiveCollab. The URL will look like:

```
https://your-domain.com/projects/2507
```

The number at the end is your `$PROJECT_ID`.

### 4. Start the PHP server

```bash
php -S localhost:8010
```

### 5. Open in browser

```
http://localhost:8010/ActiveCollab.php
```

---

## How the Image Proxy Works

ActiveCollab's API returns attachment URLs with placeholder tokens like `--THUMBNAIL-TOKEN--` instead of real JWTs. This means you cannot use the `thumbnail_url` field directly in an `<img>` tag.

This dashboard solves that by routing all image and file requests through PHP:

```
Browser requests → /ActiveCollab.php?img=21801
                 → PHP fetches from ActiveCollab API with auth token
                 → PHP streams image bytes back to browser
```

This means images load normally in the browser without any authentication issues.

**Proxy routes:**

| URL                                  | Description                    |
|--------------------------------------|--------------------------------|
| `?img=ATTACHMENT_ID`                 | Streams image with auth        |
| `?file=ATTACHMENT_ID&name=FILENAME`  | Forces authenticated download  |

---

## Project Structure

```
ActiveCollab.php    ← Single file, everything included
README.md           ← This file
```

---

## API Endpoints Used

| Endpoint                                        | Purpose                        |
|-------------------------------------------------|--------------------------------|
| `GET /api/v1/projects/:id/tasks`                | Fetch all tasks + task lists   |
| `GET /api/v1/projects/:id/tasks/:taskId`        | Fetch task details + comments  |
| `GET /api/v1/projects/:id/attachments/:id/download` | Download attachment via proxy |

---

## Customisation

### Change the port

```bash
php -S localhost:9000
```

Then open `http://localhost:9000/ActiveCollab.php`.

### Show multiple projects

Duplicate the fetch and render section with a different `$PROJECT_ID`, or turn it into a loop:

```php
$projectIds = [2507, 2508, 2509];

foreach ($projectIds as $PROJECT_ID) {
    // fetch and render tasks for each project
}
```

### Filter only open tasks

Add this after the tasks are fetched:

```php
$tasks = array_filter($tasks, fn($t) => empty($t['is_completed']));
```

---

## Troubleshooting

**Images not loading**

Make sure `$TOKEN` and `$PROJECT_ID` are correct. Check the browser console for the proxy URL and visit it directly to see the raw error.

**Curl errors**

Ensure `curl` is enabled in your PHP installation:

```bash
php -m | grep curl
```

**Blank page or API errors**

- Confirm your `$BASE_URL` has no trailing slash
- Confirm your token has read access to the project
- Enable error output at the top of the file (already on by default):

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Slow loading**

The dashboard fetches full task details one by one. For projects with many tasks this can take a few seconds. Consider caching responses to a local JSON file if speed is a concern.

---

## Security Notes

- Do not deploy this file on a public server without adding authentication
- Your API token is stored in plain text in the PHP file — keep the file private
- The proxy routes do not validate the requester's identity — anyone who can reach `localhost:8010` can proxy downloads

---

## License

MIT — free to use and modify.