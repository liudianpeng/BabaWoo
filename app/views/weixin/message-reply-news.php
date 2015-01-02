<xml>
	<ToUserName><![CDATA[<?=$to_user?>]]></ToUserName>
	<FromUserName><![CDATA[<?=$from_user?>]]></FromUserName>
	<CreateTime><?=time()?></CreateTime>
	<MsgType><![CDATA[news]]></MsgType>
	<ArticleCount><?=$count?></ArticleCount>
	<Articles>
		<?php foreach($articles as $article){ ?>
		<item>
			<Title><![CDATA[<?=$article->title?>]]></Title> 
			<Description><![CDATA[<?=$article->description?>]]></Description>
			<PicUrl><![CDATA[<?=$article->pic_url?>]]></PicUrl>
			<Url><![CDATA[<?=$article->url?>]]></Url>
		</item>
		<?php } ?>
	</Articles>
</xml> 