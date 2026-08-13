<dialog id="sunting-fail">
    <form method="post" action="/fail/<?=(int)$folder['id']?>/kemas-kini" class="dialog-form">
        <?=\SPFPU\Core\Csrf::field()?>
        <header>
            <div><p class="eyebrow">Fail sedia ada</p><h2>Sunting fail</h2></div>
            <button type="button" class="icon-button" data-dialog-close aria-label="Tutup">×</button>
        </header>
        <div class="form-grid">
            <label>Kod Rujukan<input name="reference_code" maxlength="100" value="<?=\SPFPU\Core\View::e($folder['reference_code'])?>" required></label>
            <label>Nama Fail<input name="display_name" maxlength="150" value="<?=\SPFPU\Core\View::e($folder['display_name'])?>" required></label>
        </div>
        <label>Keterangan <span>(pilihan)</span><textarea name="description" maxlength="500" rows="3"><?=\SPFPU\Core\View::e($folder['description']??'')?></textarea></label>
        <p>Tetapan sulit tidak boleh diubah selepas fail diwujudkan.</p>
        <footer>
            <button type="button" class="button quiet" data-dialog-close>Batal</button>
            <button class="button primary">Simpan perubahan</button>
        </footer>
    </form>
</dialog>
