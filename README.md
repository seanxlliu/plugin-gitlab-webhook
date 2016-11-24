Gitlab Webhook
==============

[![Build Status](https://travis-ci.org/kanboard/plugin-gitlab-webhook.svg?branch=master)](https://travis-ci.org/kanboard/plugin-gitlab-webhook)

Bind Gitlab webhook events to Kanboard automatic actions.

Author
------

- Frederic Guillot
- License MIT

Requirements
------------

- Kanboard >= 1.0.29
- Gitlab webhooks configured for a project

Installation
------------

You have the choice between 3 methods:

1. Install the plugin from the Kanboard plugin manager in one click
2. Download the zip file and decompress everything under the directory `plugins/GitlabMergeHook`
3. Clone this repository into the folder `plugins/GitlabMergeHook`

Note: Plugin folder is case-sensitive.

Documentation
-------------

Gitlab events can be connected to Kanboard automatic actions.

### List of supported events

- Gitlab commit received
- Gitlab issue opened
- Gitlab issue closed
- Gitlab issue comment created

### List of supported actions

- Create a task from an external provider
- Close a task
- Create a comment from an external provider

### Configuration

![Gitlab configuration](https://cloud.githubusercontent.com/assets/323546/20451679/47cc19b8-adca-11e6-86b2-fe4d377f41d6.png)

1. On Kanboard, go to the project settings and choose the section **Integrations**
2. Copy the Gitlab webhook url
3. On Gitlab, go to the project settings and go to the section **Webhooks**
4. Check the boxes **Push Events**, **Comments** and **Issues Events**
5. Paste the url and save

### Examples

#### Close a Kanboard task when a commit pushed to Gitlab

- Choose the event: **Gitlab commit received**
- Choose the action: **Close the task**

When one or more commits are sent to Gitlab, Kanboard will receive the information, each commit message with a task number included will be closed.

Example:

- Commit message: "Fix bug #1234"
- That will close the Kanboard task #1234

#### Create a Kanboard task when a new issue is opened on Gitlab

- Choose the event: **Gitlab issue opened**
- Choose the action: **Create a task from an external provider**

When a task is created from a Gitlab issue, the link to the issue is added to the description and the task have a new field named "Reference" (this is the Gitlab ticket number).

#### Close a Kanboard task when an issue is closed on Gitlab

- Choose the event: **Gitlab issue closed**
- Choose the action: **Close the task**

#### Reopen a Kanboard task when a closed issue is reopened on Gitlab

- Choose the event: **Gitlab issue reopened**
- Choose the action: **Open a task**

#### Create a comment on Kanboard when an issue is commented on Gitlab

- Choose the event: **Gitlab issue comment created**
- Choose the action: **Create a comment from an external provider**

If the username is the same between Gitlab and Kanboard the comment author will be assigned, otherwise there is no author.
The user also have to be member of the project in Kanboard.
