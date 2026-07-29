export default function getCookie(name) {
  if (typeof name !== "string") {
    return "";
  }

  return document.cookie
    .split("; ")
    .find((row) => row.startsWith(name + "="))
    ?.split("=")[1];
}
