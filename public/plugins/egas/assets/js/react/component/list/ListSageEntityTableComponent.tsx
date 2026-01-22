import React from "react";
import { FilterShowFieldInterface } from "../../../interface/ListSageEntityInterface";
import { getTranslations } from "../../../functions/translations";
import { useSearchParams } from "react-router-dom";
import { TOKEN } from "../../../token";
import { Tooltip } from "@mui/material";

let translations: any = getTranslations();
const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);

interface ResultInterface {
  totalCount: number;
  items: any[];
}

type State = {
  showFields: FilterShowFieldInterface[];
  hideFields: string[];
  mandatoryFields: string[];
  sageEntityName: string;
  result: ResultInterface | undefined;
  searching: boolean;
};

type State2 = {
  showFields: FilterShowFieldInterface[];
  hideFields: string[];
};

type State3 = {
  row: any;
  sageEntityName: string;
};

export const ListSageEntityTableHeaderComponent: React.FC<State2> = ({
  showFields,
  hideFields,
}) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [sort, setSort] = React.useState<any>();

  let sortField = null;
  let sortValue = null;
  if (sort) {
    sortField = Object.keys(sort)[0];
    sortValue = sort[sortField];
  }

  React.useEffect(() => {
    try {
      setSort(JSON.parse(searchParams.get("sort") ?? "null"));
    } catch (e) {
      setSort(null);
    }
  }, [searchParams]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <tr>
      <td id="cb" className="manage-column column-cb check-column">
        <label
          className="label-covers-full-cell"
          htmlFor="cb-select-all-header"
        >
          <span className="screen-reader-text">Select All.</span>
        </label>
        <input id="cb-select-all-header" type="checkbox" />
      </td>
      {showFields
        .filter((field) => !hideFields.includes(field.name))
        .map((field, index) => {
          let label = translations[field.transDomain][field.name];
          if (typeof label === "object") {
            label = label.label;
          }
          let sortableClass = "";
          let sortableOrder = "";
          if (field.name === sortField) {
            sortableClass = "sortable";
            sortableOrder = sortValue;
          }
          return (
            <th
              key={index}
              scope="col"
              id={field.name}
              className={`manage-column column-${field.name} ${sortableClass} sorted ${sortableOrder}`}
              abbr={field.name}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                }}
              >
                {!field.isFilter ? (
                  <span>{label}</span>
                ) : (
                  <a
                    onClick={(e) => {
                      e.preventDefault();
                      if (field.name === sortField) {
                        if (sortValue === "asc") {
                          setSearchParams((x) => {
                            const params = new URLSearchParams(x);
                            params.set(
                              "sort",
                              JSON.stringify({ [field.name]: "desc" }),
                            );
                            return params;
                          });
                        } else {
                          setSearchParams((x) => {
                            const params = new URLSearchParams(x);
                            params.delete("sort");
                            return params;
                          });
                        }
                      } else {
                        setSearchParams((x) => {
                          const params = new URLSearchParams(x);
                          params.set(
                            "sort",
                            JSON.stringify({ [field.name]: "asc" }),
                          );
                          return params;
                        });
                      }
                    }}
                  >
                    <span>{label}</span>
                    <span className="sorting-indicators">
                      <span
                        className="sorting-indicator asc"
                        aria-hidden="true"
                      ></span>
                      <span
                        className="sorting-indicator desc"
                        aria-hidden="true"
                      ></span>
                    </span>
                    <span className="screen-reader-text">Sort asc.</span>
                  </a>
                )}
              </div>
            </th>
          );
        })}

      <th scope="col" id="actions" className="" abbr="actions">
        <div
          style={{
            display: "flex",
            alignItems: "center",
          }}
        ></div>
      </th>
    </tr>
  );
};

export const ListSageEntityTableActionComponent: React.FC<State3> = ({
  row,
  sageEntityName,
}) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [loading, setLoading] = React.useState<boolean>(false);
  const canImport = row[`_${TOKEN}_can_import`].length === 0;
  const alreadyImported = !!row[`_${TOKEN}_post_url`];
  const messages: string[] = [...row[`_${TOKEN}_can_import`]];
  if (canImport) {
    if (alreadyImported) {
      messages.push(translations.words.itemAlreadyImported);
    } else {
      messages.push(translations.words.importItem);
    }
  }
  const importEntity = async () => {
    if (!canImport || loading) {
      return;
    }
    setLoading(true);
    const response = await fetch(
      siteUrl +
        "/index.php?rest_route=" +
        encodeURIComponent(
          `/${TOKEN}/v1/import/${sageEntityName}/${row[`_${TOKEN}_identifier`]}`,
        ) +
        "&_wpnonce=" +
        wpnonce,
    );
    setLoading(false);
    if (response.status === 200) {
      const data = await response.json();
      setSearchParams((x) => {
        const params = new URLSearchParams(x);
        params.set("newId", data.id.toString());
        return params;
      });
    } else {
      // todo toastr
    }
  };

  return (
    <>
      <Tooltip
        title={
          <>
            {messages.map((message, index) => (
              <p
                style={{
                  margin: 0,
                }}
                key={index}
              >
                {message}
              </p>
            ))}
          </>
        }
        arrow
        placement="left"
      >
        <span
          className={`dashicons dashicons-download button${!canImport ? " text-error" : ""}${loading ? " text-disabled" : ""}`}
          style={{
            paddingRight: "22px",
          }}
          onClick={importEntity}
          aria-disabled={loading}
        ></span>
      </Tooltip>

      {alreadyImported && (
        <Tooltip title={translations.words.seeItem} arrow placement="left">
          <a href={row[`_${TOKEN}_post_url`]}>
            <span
              className="dashicons dashicons-visibility button"
              style={{
                paddingRight: "22px",
              }}
            ></span>
          </a>
        </Tooltip>
      )}
    </>
  );
};

export const ListSageEntityTableComponent: React.FC<State> = ({
  showFields,
  hideFields,
  sageEntityName,
  mandatoryFields,
  result,
  searching,
}) => {
  const realShowFields = showFields.filter(
    (field) => !hideFields.includes(field.name),
  );
  return (
    <table className="wp-list-table widefat fixed striped table-view-list">
      <thead>
        <ListSageEntityTableHeaderComponent
          showFields={showFields}
          hideFields={hideFields}
        />
      </thead>
      <tbody>
        {result?.items &&
          result.items.length > 0 &&
          result?.items.map((row) => {
            const name = `row_${sageEntityName}_${row[`_${TOKEN}_identifier`]}`;
            return (
              <tr
                id={name}
                key={row[`_${TOKEN}_identifier`]}
                style={{
                  ...(searching && {
                    backgroundColor: "rgba(0,0,0,0.3)",
                  }),
                }}
              >
                <th scope="row" className="check-column">
                  <label className="label-covers-full-cell" htmlFor={name}>
                    <span className="screen-reader-text">
                      Select {row[`_${TOKEN}_identifier`]}
                    </span>
                  </label>
                  <input
                    type="checkbox"
                    name={`${sageEntityName}[]`}
                    id={name}
                    value={row[`_${TOKEN}_identifier`]}
                  />
                </th>
                {realShowFields.map((field, index) => {
                  let label = row[field.name.replace("metaData_", "_")];
                  if (typeof label === "boolean") {
                    if (label) {
                      label = translations.words.yes;
                    } else {
                      label = translations.words.no;
                    }
                  } else if (field.values) {
                    const i = row[field.name];
                    label = `[${i}]: ${field.values[i]}`;
                  } else {
                    try {
                      const date = new Date(label);
                      if (
                        date instanceof Date &&
                        !isNaN(date.getTime()) &&
                        date > new Date(1980, 0, 1) // sage was created in 1981
                      ) {
                        label = new Intl.DateTimeFormat(undefined, {
                          year: "numeric",
                          month: "2-digit",
                          day: "2-digit",
                          // timeZone: "Etc/UTC",
                        }).format(date);
                      }
                    } catch (e) {
                      // nothing
                    }
                  }
                  return (
                    <td key={index} data-colname={field.name}>
                      {label}
                    </td>
                  );
                })}
                <td
                  data-colname="actions"
                  style={{
                    textAlign: "right",
                  }}
                >
                  <ListSageEntityTableActionComponent
                    row={row}
                    sageEntityName={sageEntityName}
                  />
                </td>
              </tr>
            );
          })}
        {(!result?.items || result.items.length === 0) && (
          <tr
            style={{
              ...(searching && {
                backgroundColor: "rgba(0,0,0,0.3)",
              }),
            }}
          >
            <td
              colSpan={realShowFields.length + 2}
              style={{
                textAlign: "center",
              }}
            >
              <span className={"h5"}>
                {!result?.items
                  ? translations.words.searching
                  : translations.words.noResult}
              </span>
            </td>
          </tr>
        )}
      </tbody>
      <tfoot>
        <ListSageEntityTableHeaderComponent
          showFields={showFields}
          hideFields={hideFields}
        />
      </tfoot>
    </table>
  );
};
