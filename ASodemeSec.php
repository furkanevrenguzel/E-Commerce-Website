<?php
if(isset($_SESSION["Kullanici"])){
	if(isset($_POST["AdresSecimi"])){
		$GelenAdresSecimi		=	filtrele($_POST["AdresSecimi"]);
	}else{
		$GelenAdresSecimi		=	"";
	}
	if(isset($_POST["KargoSecimi"])){
		$GelenKargoSecimi		=	filtrele($_POST["KargoSecimi"]);
	}else{
		$GelenKargoSecimi		=	"";
	}
	if(($GelenAdresSecimi!="") and ($GelenKargoSecimi!="")){
		$SepettiGuncellemeSorgusu	=	$db_baglantisi->prepare("UPDATE sepet SET KargoId = ?, AdresId = ? WHERE UyeId = ?");
		$SepettiGuncellemeSorgusu->execute([$GelenKargoSecimi, $GelenAdresSecimi, $KullaniciID]);
		$GuncellemeKontrol			=	$SepettiGuncellemeSorgusu->rowCount();

		$StokIcinSepettekiUrunlerSorgusu	=	$db_baglantisi->prepare("SELECT * FROM sepet WHERE UyeId = ?");
		$StokIcinSepettekiUrunlerSorgusu->execute([$KullaniciID]);
		$StokIcinSepettekiUrunSayisi		=	$StokIcinSepettekiUrunlerSorgusu->rowCount();
		$StokIcinSepettiKayitlar			=	$StokIcinSepettekiUrunlerSorgusu->fetchAll(PDO::FETCH_ASSOC);

		if($StokIcinSepettekiUrunSayisi>0){
			foreach($StokIcinSepettiKayitlar as $StokIcinSepettekiSatirlar){
				$StokIcinSepetIdsi						=	$StokIcinSepettekiSatirlar["id"];
				$StokIcinSepettekiUrununUrunIdsi		=	$StokIcinSepettekiSatirlar["UrunId"];
				$StokIcinSepettekiUrununAdedi			=	$StokIcinSepettekiSatirlar["UrunAdedi"];

        $StokIcinUrunBilgileriSorgusu	=	$db_baglantisi->prepare("SELECT * FROM urunler WHERE id = ? LIMIT 1");
    		$StokIcinUrunBilgileriSorgusu->execute([$StokIcinSepettekiUrununUrunIdsi]);
    		$StokKaydi					=	$StokIcinUrunBilgileriSorgusu->fetch(PDO::FETCH_ASSOC);
    		$StokIcinUrununStokAdedi	=	$StokKaydi["StokSayisi"];


				if($StokIcinUrununStokAdedi==0){
					$SepetSilSorgusu		=	$db_baglantisi->prepare("DELETE FROM sepet WHERE id = ? AND UyeId = ? LIMIT 1");
					$SepetSilSorgusu->execute([$StokIcinSepetIdsi, $KullaniciID]);
				}elseif($StokIcinSepettekiUrununAdedi>$StokIcinUrununStokAdedi){
					$SepetGuncellemeSorgusu		=	$db_baglantisi->prepare("UPDATE sepet SET UrunAdedi= ? WHERE id = ? AND UyeId = ? LIMIT 1");
					$SepetGuncellemeSorgusu->execute([$StokIcinUrununStokAdedi, $StokIcinSepetIdsi, $KullaniciID]);
				}
			}
		}

		$SepettekiUrunlerSorgusu	=	$db_baglantisi->prepare("SELECT * FROM sepet WHERE UyeId = ? ORDER BY id DESC");
		$SepettekiUrunlerSorgusu->execute([$KullaniciID]);
		$SepettekiUrunSayisi		=	$SepettekiUrunlerSorgusu->rowCount();
		$SepettiKayitlar			=	$SepettekiUrunlerSorgusu->fetchAll(PDO::FETCH_ASSOC);

		if($SepettekiUrunSayisi>0){
			$SepettekiToplamUrunSayisi			=	0;
			$SepettekiToplamFiyat				=	0;
			$SepettekiToplamKargoFiyati			=	0;
			$SepettekiToplamKargoFiyatiHesapla	=	0;

			foreach($SepettiKayitlar as $SepetSatirlari){
				$SepetIdsi						=	$SepetSatirlari["id"];
				$SepettekiUrununIdsi			=	$SepetSatirlari["UrunId"];
				$SepettekiUrununAdedi			=	$SepetSatirlari["UrunAdedi"];

				$UrunBilgileriSorgusu			=	$db_baglantisi->prepare("SELECT * FROM urunler WHERE id = ? LIMIT 1");
				$UrunBilgileriSorgusu->execute([$SepettekiUrununIdsi]);
				$UrunKaydi						=	$UrunBilgileriSorgusu->fetch(PDO::FETCH_ASSOC);
					$UrununFiyati			=	$UrunKaydi["UrunFiyati"];
					$UrununParaBirimi		=	$UrunKaydi["ParaBirimi"];
					$UrununKargoUcreti		=	$UrunKaydi["KargoUcreti"];

					$UrunFiyatiHesapla				=	$UrununFiyati;
					$UrunFiyatiBicimlendir			=	fiyatYaz($UrununFiyati);

				$UrunToplamFiyatiHesapla				=	($UrunFiyatiHesapla*$SepettekiUrununAdedi);
				$UrunToplamFiyatiBicimlendir			=	fiyatYaz($UrunToplamFiyatiHesapla);

				$SepettekiToplamUrunSayisi				+=	$SepettekiUrununAdedi;
				$SepettekiToplamFiyat					+=	($UrunFiyatiHesapla*$SepettekiUrununAdedi);

				$SepettekiToplamKargoFiyatiHesapla		+=	($UrununKargoUcreti*$SepettekiUrununAdedi);
				$SepettekiToplamKargoFiyatiBicimlendir	=	fiyatYaz($SepettekiToplamKargoFiyatiHesapla);
			}

			if($SepettekiToplamFiyat>=$ucretsizKargo){
				$SepettekiToplamKargoFiyatiHesapla		=	0;
				$SepettekiToplamKargoFiyatiBicimlendir	=	fiyatYaz($SepettekiToplamKargoFiyatiHesapla);

				$OdenecekToplamTutariBicimlendir		=	fiyatYaz($SepettekiToplamFiyat);
			}else{
				$OdenecekToplamTutariHesapla			=	($SepettekiToplamFiyat+$SepettekiToplamKargoFiyatiHesapla);
				$OdenecekToplamTutariBicimlendir		=	fiyatYaz($OdenecekToplamTutariHesapla);
			}

			$IkiTaksitAylikOdemeTutari			=	number_format(($SepettekiToplamFiyat/2), "2", ".", ",");
			$UcTaksitAylikOdemeTutari			=	number_format(($SepettekiToplamFiyat/3), "2", ".", ",");
			$DortTaksitAylikOdemeTutari			=	number_format(($SepettekiToplamFiyat/4), "2", ".", ",");
		}
?>
<form action="index.php?sk=100" method="post">
	<table width=100% align="center" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td width="1065" valign="top">
				<table width="1065" align="center" border="0" cellpadding="0" cellspacing="0">
					<tr height="40">
						<td style="color:#ff6666"><h3>Al????veri?? Sepeti</h3></td>
					</tr>
					<tr height="30">
						<td valign="top" style="border-bottom: 1px dashed #ff00aa;"><i>??deme T??r?? Se??imini A??a????dan Belirtebilirsin.</i></td>
					</tr>
					<tr height="10">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>
					<tr height="40">
						<td align="left" style="background: #ff9999; font-weight: bold; color: black;">&nbsp;??deme T??r?? Se??imi</td>
					</tr>
					<tr height="10">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>

					<tr>
						<td align="left">
							<table width="800" align="center" border="0" cellpadding="0" cellspacing="0">
								<tr>
									<td width="390" align="left"><table width="400" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
										<tr>
											<td>&nbsp;</td>
										</tr>
										<tr>
											<td align="center"><img src="resimler/buyukkart.png" border="0"></td>
										</tr>
										<tr>
											<td>&nbsp;</td>
										</tr>
										<tr>
											<td align="center"><input type="radio" name="OdemeTuruSecimi" value="Kredi Kart??" checked="checked" onClick="$.kartsecilince();"></td>
										</tr>
										<tr>
											<td>&nbsp;</td>
										</tr>
									</table></td>

									<td width="20">&nbsp;</td>

									<td width="390" align="left"><table width="400" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
										<tr>
											<td>&nbsp;</td>
										</tr>
										<tr>
											<td align="center"><img src="resimler/buyukbanka.png" border="0"></td>
										</tr>
										<tr>
											<td>&nbsp;</td>
										</tr>
										<tr>
											<td align="center"><input type="radio" name="OdemeTuruSecimi" value="Banka Havalesi" onClick="$.eftsecilince();"></td>
										</tr>
										<tr>
											<td>&nbsp;</td>
										</tr>
									</table></td>
								</tr>
							</table>
						</td>
					</tr>

					<tr height="10">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>

					<tr height="40" class="kartla">
						<td height="40" width="1065" align="left" bgcolor="#ff9999" style="color: black"><b>&nbsp;Kredi Kart?? ??le ??deme</b></td>
					</tr>
					<tr height="10" class="kartla">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>
					<tr height="30" class="kartla">
						<td height="30" width="1065" align="left"><i>??deme i??leminizi a??a????daki banka kartlar?? dahilinde belirtilen se??enekler ile tamamlayabilirsiniz.</i></td>
					</tr>
					<tr height="10" class="kartla">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>
					<tr class="kartla">
						<td><table width="1065" align="center" border="0" cellpadding="0" cellspacing="0">
							<tr>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiAxessCard.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
								<td width="11">&nbsp;</td>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiBonusCard.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
								<td width="11">&nbsp;</td>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiCardFinans.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
								<td width="10">&nbsp;</td>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiMaximumCard.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
							</tr>
							<tr>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiWorldCard.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
								<td width="11">&nbsp;</td>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiParafCard.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
								<td width="11">&nbsp;</td>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiATMKarti.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
								<td width="10">&nbsp;</td>
								<td width="192"><table width="192" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
									<tr>
										<td>&nbsp;</td>
									</tr>
									<tr>
										<td align="center"><img src="resimler/OdemeSecimiDigerKartlar.png" border="0"></td>
									</tr>
									<tr>
										<td>&nbsp;</td>
									</tr>
								</table></td>
							</tr>
						</table></td>
					</tr>

					<tr height="40" class="kartla">
						<td height="40" width="1065" align="left" bgcolor="#ff9999" style="color: black"><b>&nbsp;Taksit Se??imi</b></td>
					</tr>
					<tr height="10" class="kartla">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>
					<tr height="40" class="kartla">
						<td height="40" width="800" align="left"><i>L??tfen ??deme i??leminde uygulanmas??n?? istedi??iniz taksit say??s??n?? se??iniz.</i></td>
					</tr>
					<tr height="30" class="kartla">
						<td><table width="1065" align="center" border="0" cellpadding="0" cellspacing="0">
							<tr height="30">
								<td width="25" align="left" style="border-bottom: 1px dashed #ff00aa;"><input type="radio" name="TaksitSecimi" value="1" checked="checked"></td>
								<td width="375" align="left" style="border-bottom: 1px dashed #ff00aa;">Tek ??ekim</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;">1 x <?php echo $OdenecekToplamTutariBicimlendir; ?> TL</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;"><?php echo $OdenecekToplamTutariBicimlendir; ?> TL</td>
							</tr>
						</table></td>
					</tr>
					<tr height="30" class="kartla">
						<td><table width="1065" align="center" border="0" cellpadding="0" cellspacing="0">
							<tr height="30">
								<td width="25" align="left" style="border-bottom: 1px dashed #ff00aa;"><input type="radio" name="TaksitSecimi" value="2"></td>
								<td width="375" align="left" style="border-bottom: 1px dashed #ff00aa;">2 Taksit</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;">2 x <?php echo $IkiTaksitAylikOdemeTutari; ?> TL</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;"><?php echo $OdenecekToplamTutariBicimlendir; ?> TL</td>
							</tr>
						</table></td>
					</tr>
					<tr height="30" class="kartla">
						<td><table width="1065" align="center" border="0" cellpadding="0" cellspacing="0">
							<tr height="30">
								<td width="25" align="left" style="border-bottom: 1px dashed #ff00aa;"><input type="radio" name="TaksitSecimi" value="3"></td>
								<td width="375" align="left" style="border-bottom: 1px dashed #ff00aa;">3 Taksit</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;">3 x <?php echo $UcTaksitAylikOdemeTutari; ?> TL</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;"><?php echo $OdenecekToplamTutariBicimlendir; ?> TL</td>
							</tr>
						</table></td>
					</tr>
					<tr height="30" class="kartla">
						<td><table width="1065" align="center" border="0" cellpadding="0" cellspacing="0">
							<tr height="30">
								<td width="25" align="left" style="border-bottom: 1px dashed #ff00aa;"><input type="radio" name="TaksitSecimi" value="4"></td>
								<td width="375" align="left" style="border-bottom: 1px dashed #ff00aa;">4 Taksit</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;">4 x <?php echo $DortTaksitAylikOdemeTutari; ?> TL</td>
								<td width="200" align="right" style="border-bottom: 1px dashed #ff00aa;"><?php echo $OdenecekToplamTutariBicimlendir; ?> TL</td>
							</tr>
						</table></td>
					</tr>
					<tr height="40" class="eftyle" style="display: none;">
						<td height="40" width="1065" align="left" bgcolor="#ff9999" style="color: black";><b>&nbsp;Banka Havalesi / EFT ??le ??deme</b></td>
					</tr>
					<tr height="10" class="eftyle" style="display: none;">
						<td style="font-size: 10px;">&nbsp;</td>
					</tr>
					<tr height="40" class="eftyle" style="display: none;">
						<td height="40" width="1065" align="left">Banka Havalesi / EFT ile ??r??n sat??n alabilmek i??in ??ncelikle al????veri?? sepeti tutar??n?? <b>Banka Hesaplar??m??z</b> sayfas??nda bulunan herhangi bir hesaba ??deme yapt??ktan sonra <b>Havale Bildirim Formu</b> arac??l?????? ile l??tfen taraf??m??za bilgi veriniz. <b>??deme Yap</b> butonuna t??klad??????n??z anda sipari??iniz sisteme kay??t edilecektir.</td>
					</tr>
          <tr>
            <td>&nbsp;</td>
          </tr>
				</table>
			</td>

			<td width="15">&nbsp;</td>

			<td width="250" valign="top"><table width="250" align="center" border="0" cellpadding="0" cellspacing="0">
				<tr height="40">
					<td  style="color:#ff6666" align="right"><h3>Sipari?? ??zeti</h3></td>
				</tr>
				<tr height="30">
					<td valign="top" style="border-bottom: 1px dashed #ff00aa; font-size: 18px;" align="right">Toplam <b style="color: #800080;"><?php echo $SepettekiToplamUrunSayisi; ?></b> Adet ??r??n</td>
				</tr>
				<tr height="5">
					<td height="5" style="font-size: 5px;">&nbsp;</td>
				</tr>
				<tr>
					<td align="right">??denecek Tutar (KDV Dahil)</td>
				</tr>
				<tr>
					<td align="right" style="font-size: 25px; font-weight: bold;"><?php echo $OdenecekToplamTutariBicimlendir; ?> TL</td>
				</tr>
				<tr height="10">
					<td style="font-size: 10px;">&nbsp;</td>
				</tr>
				<tr>
					<td align="right">??r??nler Toplam Tutar?? (KDV Dahil)</td>
				</tr>
				<tr>
					<td align="right" style="font-size: 25px; font-weight: bold;"><?php echo fiyatYaz($SepettekiToplamFiyat); ?> TL</td>
				</tr>
				<tr height="10">
					<td style="font-size: 10px;">&nbsp;</td>
				</tr>
				<tr>
					<td align="right">Kargo Tutar?? (KDV Dahil)</td>
				</tr>
				<tr>
					<td align="right" style="font-size: 25px; font-weight: bold;"><?php echo $SepettekiToplamKargoFiyatiBicimlendir; ?> TL</td>
				</tr>
				<tr height="10">
					<td style="font-size: 10px;">&nbsp;</td>
				</tr>
				<tr>
					<td align="right"><input type="submit" value="??deme Yap" class="OdemeSecimiButonu"></td>
				</tr>
        <tr height="120">
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td><img src="resimler/guvenliav.png"></td>
        </tr>
			</table></td>
		</tr>
	</table>
</form>
<?php
	}else{
		header("Location:index.php");
		exit();
	}
}else{
	header("Location:index.php");
	exit();
}
?>
