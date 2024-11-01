# TODO

The following list comprises ideas, suggestions, and known issues, all of which are in consideration for possible implementation in future releases.

***This is not a roadmap or a task list.*** Just because something is listed does not necessarily mean it will ever actually get implemented. Some might be bad ideas. Some might be impractical. Some might either not benefit enough users to justify the effort or might negatively impact too many existing users. Or I may not have the time to devote to the task.

* Support popular social media plugins to also prevent broadcasting post via tweets, etc.
* Support Jetpack's Publicize. Not sure if the following suggestion is enough:
  https://jetpack.com/2013/05/03/remove-publicize-meta-box-from-the-new-post/
* Gutenberg: Add publish confirmation panel widget to indicate if the post will be published silently or not.
* Get column and field names used within assets/js/quick-edit.js via localized variables and not hardcoded
* Add more unit tests
* Instead of keying off whether post is published or not, should it key off more specifically on post being either a draft or pending (in case there are other published-like post statuses)?

Feel free to make your own suggestions or champion for something already on the list (via the [plugin's support forum on WordPress.org](https://wordpress.org/support/plugin/silent-publish/) or on [GitHub](https://github.com/coffee2code/silent-publish/) as an issue or PR).