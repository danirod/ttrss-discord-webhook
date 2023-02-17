<?php

class discord_hook extends Plugin {

	private $host;

	function about() {
		return array(1.0, "Discord Webhook Filter", "danirod");
	}

	function flags() {
		return array("needs_curl" => true);
	}

	function api_version() {
		return 2;
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER_ACTION, $this);

		$host->add_filter_action($this, "discord_hook_trigger", "Send article to the Discord webhook");
	}

	function save() {
		$discord_webhook_url = db_escape_string($_POST["discord_webhook_url"]);
		$this->host->set($this, "discord_webhook_schema", 1); /* futureproof! */
		$this->host->set($this, "discord_webhook_url", $discord_webhook_url);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") {
			return;
		}

		$discord_webhook_url = $this->host->get($this, "discord_webhook_url");

		print '
			<div dojoType="dijit.layout.AccordionPane" title="Discord Webhooks">
				<p>Use these settings to configure the URL to send articles to whenever the filter gets executed.</p>
				<form dojoType="dijit.form.Form">
					<script type="dojo/method" event="onSubmit" args="evt">
						evt.preventDefault();
						if (this.validate()) {
							Notify.progress('Saving discord_webhook configuration...', true);
							xhr.post("backend.php", this.getValues(), (reply) => {
								Notify.info(reply);
							})							
						}
					</script>

					<input dojoType="dijit.form.TextBox" style="display: none" name="op" value="pluginhandler" />
					<input dojoType="dijit.form.TextBox" style="display: none" name="plugin" value="discord_hook" />
					<input dojoType="dijit.form.TextBox" style="display: none" name="method" value="save" />

					<table class="prefPrefsList">
						<tr>
							<td width="40%">Webhook URL</td>
							<td class="prefValue">
								<input dojoType="dijit.form.ValidationTextBox" required="1" name="discord_webhook_url"
									   value="' . $discord_webhook_url . '" placeholder="https://discord.com/api/webhook/" />
							</td>
						</tr>
					</table>

					<p><button dojoType="dijit.form.Button" type="submit">Save changes</button></p>
				</form>
			</div>
		';
	}

	function hook_article_filter_action($article, $action) {
		$discord_webhook_url = $this->host->get($this, "discord_webhook_url");
		if ($discord_webhook_url) {
			$this->send_article($discord_webhook_url, $article['title'], $article['link']);
		}
		return $article; // leave untouched!
	}

	function send_article($webhook, $title, $url) {
		/* Safety checks. */
		if (!$webhook || !$url) {
			return;
		}

		/* Build payload. */
		if ($title) {
			$payload = ["content" => "**$title**\n$url"];
		} else {
			/* Should not happen, but! */
			$payload = ["content" => "$url"];
		}

		/* Submit payload. */
		$curl = curl_init($webhook);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		$result = curl_exec($curl);
		curl_close($curl);
	}
}
