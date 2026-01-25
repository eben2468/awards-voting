# Organizer Guide: Awards Voting System

Welcome to the **Awards Voting System** Organizer Portal. This guide provides comprehensive instructions for managing your awards events, from initial registration to processing payouts.

---

## Table of Contents
1. [Account Registration & Setup](#account-registration--setup)
2. [Organizer Portal Overview](#organizer-portal-overview)
3. [Managing Events](#managing-events)
4. [Managing Categories](#managing-categories)
5. [Managing Nominees](#managing-nominees)
6. [Monitoring Votes & Results](#monitoring-votes--results)
7. [Financials & Payouts](#financials--payouts)
8. [Reports & Analytics](#reports--analytics)
9. [Account Settings](#account-settings)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)

---

## 1. Account Registration & Setup

### Registration Process
1. Navigate to the **Registration Page** (`register.php`).
2. Fill in your **Full Name**, **Email Address**, and **Phone Number**.
3. Create a secure **Password** (minimum 8 characters).
4. Agree to the **Terms & Conditions** and **Privacy Policy**.
5. Click **Create Account**.
6. Upon successful registration, you will be redirected to the login page.

### Login
1. Navigate to the **Login Page** (`login.php`).
2. Enter your registered email and password.
3. Click **Login** to access your dashboard.

---

## 2. Organizer Portal Overview

The Organizer Portal is your central hub for managing all aspects of your voting events.

### UI Highlights
- **Dashboard**: Features a clean, card-based layout with real-time statistics.
- **Sidebar**: A dark-themed navigation rail on the left for quick access to all modules.
- **Top Bar**: Displays the current page title and your user profile dropdown.

### Sidebar Menu
- **Dashboard**: Overview of all activities.
- **Events**: Manage your award ceremonies.
- **Categories**: Group nominees within events.
- **Nominees**: Manage the candidates for each category.
- **Votes**: Real-time log of all voting transactions.
- **Results**: Visual rankings and analytics.
- **Payouts**: Manage earnings and withdrawals.
- **Reports**: Export data to CSV.
- **Account**: Manage your profile and settings.

---

## 3. Managing Events

Events are the top-level containers for your awards.

### Creating an Event
1. Go to **Events** > **Add New** or click **New Event** on the dashboard.
2. **Title**: Enter the name of your award ceremony.
3. **Description**: Provide details about the event.
4. **Dates**: Set the **Start Date** and **End Date** for voting.
5. **Banner Image**: Upload a high-quality banner (JPG, PNG, WebP, max 5MB).
6. **Status**: 
   - `Draft`: Not visible to the public. Use this while setting up.
   - `Active`: Visible and open for voting (if within the start/end dates).
   - `Completed`: Voting closed, but results remain visible.
7. **Vote Price**: Set the cost per vote in GHS (e.g., 1.00).
8. Click **Create Event**.

### UI Tip
> In the **Events** list, you can see a summary of total votes and the end date for each event at a glance.

---

## 4. Managing Categories

Categories group nominees within an event (e.g., "Artist of the Year").

### Adding a Category
1. Go to **Categories** > **Add New**.
2. Select the **Event** this category belongs to.
3. Enter the **Category Name**.
4. **Display Order**: Use numbers (0, 1, 2...) to control the order in which categories appear on the public voting page.
5. Set the status to **Active**.
6. Click **Create Category**.

---

## 5. Managing Nominees

Nominees are the individuals or groups being voted for.

### Adding a Nominee
1. Go to **Nominees** > **Add New**.
2. Select the **Category** (and by extension, the Event).
3. Enter the **Nominee Name**.
4. **Shortcode**: Assign a unique code for USSD/SMS voting (e.g., `ART01`). This is what voters will type to vote.
5. **Photo**: Upload a profile picture (will be cropped to a circle in the UI).
6. **Description**: Add a brief bio or reason for nomination.
7. Click **Create Nominee**.

---

## 6. Monitoring Votes & Results

### Monitoring Votes
- Go to the **Votes** section to see a real-time log of all votes cast.
- **UI Highlight**: The votes table shows the voter's IP address, the nominee they voted for, and the exact timestamp.
- You can filter by event to see specific logs.

### Viewing Results
- Go to the **Results** section.
- **Visual Rankings**: Results are displayed with progress bars showing the relative lead of each nominee.
- **Trophy Icons**: The top 3 nominees are automatically highlighted with Gold, Silver, and Bronze trophy icons.
- **Analytics**: View the total vote count and the percentage share for each nominee in their category.

---

## 7. Financials & Payouts

Manage your earnings from paid voting.

### Setting Up Settlement Methods
1. Go to **Payouts**.
2. Click **Add Method**.
3. Choose **Mobile Money** (MTN, Telecel, etc.) or **Bank Account**.
4. Provide the **Provider/Bank Name**, **Account Name**, and **Account Number**.
5. Click **Save Method**.

### Requesting a Payout
1. In the **Payouts** section, click **Request Payout**.
2. Select the **Event** (the system shows the available balance for each).
3. Choose your **Settlement Method**.
4. Enter the **Amount** (up to the available balance).
5. Click **Submit Request**.
6. **Payout Statuses**:
   - `Pending`: Request received.
   - `Approved`: Admin has verified the request.
   - `Processed`: Funds have been sent to your account.
   - `Rejected`: Request denied (check notifications for reasons).

---

## 8. Reports & Analytics

Generate data for offline analysis or record-keeping.

### Exporting Reports
1. Go to **Reports**.
2. **Votes Report**: A detailed CSV including Vote ID, User, Nominee, Category, Event, and Timestamp.
3. **Results Report**: A summary CSV including Rank, Nominee, Shortcode, Category, Event, Votes, and Percentage.
4. Use the dropdown to filter by a specific event before exporting.

---

## 9. Account Settings

### Profile Management
- Update your **Full Name**, **Email**, and **Phone Number**.
- Ensure your email is correct as it is used for important system notifications.

### Settings
- Change your password regularly to maintain account security.

---

## 10. Best Practices

- **High-Quality Images**: Use clear, professional images for event banners (1200x400 recommended) and nominee photos (square 400x400 recommended).
- **Clear Shortcodes**: Use short, memorable shortcodes (e.g., `VOTE1`, `VOTE2`) for easier USSD voting.
- **Regular Monitoring**: Check your results and payout status regularly.
- **Draft Mode**: Keep events in `Draft` mode until all categories and nominees are fully set up.
- **Promotion**: Share your event link on social media to drive more votes.

---

## 11. Troubleshooting

- **Image Upload Fails**: Ensure the image is under 5MB and in a supported format (JPG, PNG, WebP).
- **Cannot Request Payout**: Ensure you have added a settlement method and have an available balance in the selected event.
- **Nominee Not Showing**: Check if the nominee, their category, and the event are all set to **Active** status.
- **Voting Not Working**: Verify that the current date is between the event's **Start Date** and **End Date**.
- **USSD Shortcode Conflicts**: Ensure each nominee has a unique shortcode across the entire system.

---
*For further assistance, please contact the system administrator at votehub99@gmail.com.*
