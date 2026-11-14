<template>
  <header class="public-brand">
    <a class="public-brand__link" href="/">
      <img
        alt=""
        class="public-brand__logo"
        height="36"
        src="/divine-community-space.svg"
        width="36"
      >

      <span class="public-brand__name">Divine Community Space</span>
    </a>
  </header>
</template>

<style scoped>
.public-brand {
  display: flex;
  align-items: center;
  padding: 14px 0 20px;
}
.public-brand__link {
  display: inline-flex;
  gap: 12px;
  align-items: center;
  color: inherit;
  text-decoration: none;
}
.public-brand__logo {
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(20, 34, 58, 0.25);
}
.public-brand__name {
  font-size: 1.0625rem;
  font-weight: 650;
  letter-spacing: 0.01em;
}
</style>
