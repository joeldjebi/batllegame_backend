<div>
    <label class="flex items-start gap-2.5 text-sm text-slate-600 dark:text-slate-300">
        <input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20 dark:bg-white/5">
        <span>J'organise des compétitions dans le respect des artistes et du public, et j'accepte que Battle Game vérifie mon organisateur.</span>
    </label>
    @error('terms')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
