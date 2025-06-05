import React from "react";
import { ResultTableInterface } from "./ListSageEntityComponent";
import { getTranslations } from "../../../functions/translations";
import { useSearchParams } from "react-router-dom";

let translations: any = getTranslations();

type State = {
  result: ResultTableInterface | undefined;
  paginationRange: number[];
  defaultPerPage: number;
};

export const ListSageEntityPagingComponent: React.FC<State> = ({
  result,
  paginationRange,
  defaultPerPage,
}) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const getCurrentPage = () => {
    return Number(searchParams.get("paged") ?? 1);
  };
  const currentPage = getCurrentPage();
  const [page, setPage] = React.useState<string>(currentPage.toString());
  const [perPage, setPerPage] = React.useState<string>(() => {
    let result = Number(
      searchParams.get("per_page") ?? defaultPerPage,
    ).toString();
    if (!paginationRange.includes(Number(result))) {
      result = paginationRange[0].toString();
    }
    return result;
  });

  const totalCount = Number(result?.totalCount ?? 0);
  const maxPage = Math.ceil(totalCount / Number(perPage));
  const canGoBack = Number(currentPage) !== 1;
  const canGoNext = Number(currentPage) !== Number(maxPage);

  React.useEffect(() => {
    if (Number(page) > maxPage) {
      setPage(maxPage.toString());
      return;
    }
    const timeoutTyping = setTimeout(() => {
      setSearchParams((x) => {
        const params = new URLSearchParams(x);
        let p = Number(page);
        if (p < 1) {
          p = 1;
        }
        params.set("paged", p.toString());
        return params;
      });
    }, 500);
    return () => clearTimeout(timeoutTyping);
  }, [page]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    setSearchParams((x) => {
      const params = new URLSearchParams(x);
      params.set("per_page", Number(perPage).toString());
      return params;
    });
  }, [perPage]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    setPage(getCurrentPage().toString());
  }, [searchParams]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div
      className={`tablenav-pages${totalCount <= Number(perPage) ? " one-page" : ""}`}
    >
      <span className="displaying-num">
        {totalCount.toLocaleString()} {translations.words.items}
      </span>
      <span className="pagination-links">
        {[1, currentPage - 1].map((p, i) => {
          const isFirstPage = i === 0;
          const label = isFirstPage ? "First page" : "Previous page";
          const icon = isFirstPage ? "«" : "‹";
          const className = isFirstPage
            ? "first-page button"
            : "previous-page button";

          return canGoBack ? (
            <a
              key={i}
              className={className}
              onClick={(e) => {
                e.preventDefault();
                setSearchParams((x) => {
                  const params = new URLSearchParams(x);
                  params.set("paged", p.toString());
                  return params;
                });
              }}
            >
              <span className="screen-reader-text">{label}</span>
              <span aria-hidden="true">{icon}</span>
            </a>
          ) : (
            <span
              key={i}
              className="tablenav-pages-navspan button disabled"
              aria-hidden="true"
            >
              {icon}
            </span>
          );
        })}
        <span className="paging-input">
          <label htmlFor="current-page-selector" className="screen-reader-text">
            Current page
          </label>
          <input
            className="current-page"
            id="current-page-selector"
            type="text"
            name="paged"
            value={page}
            onChange={(e) => {
              setPage(e.target.value);
            }}
            size={4}
            aria-describedby="table-paging"
          />
          <span className="tablenav-paging-text">
            {translations.words.outOf}{" "}
            <span className="total-pages">{maxPage.toLocaleString()}</span>
          </span>
        </span>
        {[currentPage + 1, maxPage].map((p, i) => {
          const isNextPage = i === 0;
          const label = isNextPage ? "Next page" : "Last page";
          const icon = isNextPage ? "›" : "»";
          const className = isNextPage
            ? "next-page button"
            : "last-page button";

          return canGoNext ? (
            <a
              key={`next-${i}`}
              className={className}
              onClick={(e) => {
                e.preventDefault();
                setSearchParams((x) => {
                  const params = new URLSearchParams(x);
                  params.set("paged", p.toString());
                  return params;
                });
              }}
            >
              <span className="screen-reader-text">{label}</span>
              <span aria-hidden="true">{icon}</span>
            </a>
          ) : (
            <span
              key={`next-${i}`}
              className="tablenav-pages-navspan button disabled"
              aria-hidden="true"
            >
              {icon}
            </span>
          );
        })}
      </span>
      <label className="screen-reader-text" htmlFor="per_page">
        Per page
      </label>
      <select
        name="per_page"
        id="per_page"
        value={perPage}
        onChange={(e) => {
          setPerPage(e.target.value);
        }}
      >
        {paginationRange.map((r) => {
          return (
            <option value={r} key={r}>
              {r}
            </option>
          );
        })}
      </select>
    </div>
  );
};
