export function setHtmlFromAppState(appState: any, dom: JQuery) {
  console.log(appState);
  $(dom).html("");
  $(dom).parent().removeClass("hidden");
  createProgressBar(dom, 33);
}

function createProgressBar(dom: JQuery, percent: number) {
  const progressBarContainer = $('<div class="progress-bar-light-grey progress-bar-round-large"></div>').appendTo(dom);
  $('<div class="progress-bar-container progress-bar-blue progress-bar-round-large" style="width:' + percent + '%">' + percent + '%</div>').appendTo(progressBarContainer);
  return progressBarContainer;
}
